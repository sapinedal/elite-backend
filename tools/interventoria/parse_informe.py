#!/usr/bin/env python3
"""
Lector de los informes semanales de interventoría (Equiproyectos S.A.S, formato EQP-F-IS).

Uso:  parse_informe.py <ruta.pdf> [--fotos <carpeta>]
      Imprime un JSON con los datos del informe en stdout. Con --fotos guarda el registro fotográfico
      en esa carpeta (foto-01.jpg, foto-02.jpg...).

Los informes son PDF digitales exportados desde Excel, así que se leen sin OCR con pdfplumber.
El diseño cambia un poco entre semanas (capítulos que aparecen o desaparecen, tablas que se parten),
por eso cada sección se busca de forma tolerante y lo que no se encuentra queda vacío en lugar de fallar.
"""
from __future__ import annotations

import json
import logging
import re
import sys
import unicodedata
from pathlib import Path
from datetime import date

import pdfplumber

PARSER_VERSION = 2

# Fotos: se recortan tal como se ven en la página (orientación y encuadre correctos) a esta resolución
PHOTO_DPI = 220
PHOTO_MIN_POINTS = 40  # descarta franjas y adornos del diseño

MONTHS = {
    "enero": 1, "febrero": 2, "marzo": 3, "abril": 4, "mayo": 5, "junio": 6, "julio": 7,
    "agosto": 8, "septiembre": 9, "setiembre": 9, "octubre": 10, "noviembre": 11, "diciembre": 12,
}

DATE = r"\d{1,2}/\d{1,2}/\d{2,4}"
PCT = r"\d{1,3}(?:[.,]\d+)?\s*%"
CHAPTER_LINE = re.compile(
    rf"^(?P<n>\d{{1,2}})\s+(?P<nombre>.+?)\s+(?P<inicio>{DATE})\s+(?P<fin>{DATE})\s+"
    rf"(?P<anterior>{PCT})\s+(?P<actual>{PCT})\s*(?P<resto>.*)$"
)
POLICY_LINE = re.compile(
    r"^(?P<amparo>.+?)\s+(?P<numero>[A-Z0-9][A-Z0-9\-]*)\s+\$\s*(?P<valor>[\d\.\s]+(?:,\d+)?)\s+"
    r"(?P<inicio>\S+)\s+(?P<fin>\S+)\s+(?P<estado>VIGENTE|VENCIDA|POR VENCER)\s*$"
)
POLICY_HEADER = re.compile(r"^(?P<contratista>.+?)\s+P[ÓO]LIZA NRO\s+VLR ASEGURADO\s+INICIO\s+FIN\s+ALERTA\s*$")

SECTION_HEADERS = {
    "comite": "TEMAS TRATADOS EN COMITÉ",
    "actividades": "ACTIVIDADES EJECUTADAS - SEMANA",
    "observaciones": "OBSERVACIONES Y/O COMPROMISOS PENDIENTES",
}
STOP_HEADERS = ("REGISTRO FOTOGRAFICO", "REGISTRO FOTOGRÁFICO", "SEGUIMIENTO AL PRESUPUESTO", *SECTION_HEADERS.values())


def squash(text: str | None) -> str:
    return re.sub(r"\s+", " ", text or "").strip()


def to_number(text: str | None) -> float | None:
    """'$ 2 2.454.184.688,00' -> 22454184688.0 ; '65,82%' -> 65.82"""
    if not text:
        return None
    digits = re.sub(r"[^\d,\.]", "", text.replace(" ", ""))
    if not digits:
        return None
    digits = digits.replace(".", "").replace(",", ".")
    try:
        return float(digits)
    except ValueError:
        return None


def to_iso(text: str | None) -> str | None:
    """'17/11/26' o '30/06/2027' -> ISO; si no es una fecha válida devuelve None."""
    m = re.fullmatch(r"(\d{1,2})/(\d{1,2})/(\d{2,4})", (text or "").strip())
    if not m:
        return None
    d, mo, y = (int(x) for x in m.groups())
    y = y + 2000 if y < 100 else y
    try:
        return date(y, mo, d).isoformat()
    except ValueError:
        return None


def spanish_date_to_iso(text: str | None) -> str | None:
    """'martes, 17 de noviembre de 2026' -> '2026-11-17'"""
    m = re.search(r"(\d{1,2})\s+de\s+([a-záéíóú]+)\s+de\s+(\d{4})", (text or "").lower())
    if not m or m.group(2) not in MONTHS:
        return None
    try:
        return date(int(m.group(3)), MONTHS[m.group(2)], int(m.group(1))).isoformat()
    except ValueError:
        return None


def is_garbled(text: str) -> bool:
    """Texto vertical de Excel que el PDF entrega letra por letra ('P IN A D G E O ...')."""
    tokens = text.split()
    single_letters = sum(1 for t in tokens if len(t) == 1 and t.isalpha())
    return len(tokens) >= 6 and single_letters >= 5 and single_letters / len(tokens) > 0.3


def readable_prefix(text: str) -> str | None:
    """Parte legible de un nombre que se vuelve ilegible ('CONSTRUAPLICAMOS J y J d e S A ...' -> 'CONSTRUAPLICAMOS')."""
    words = []
    for token in text.split():
        if len(token) == 1 and token.isalpha() and words:
            break
        words.append(token)
    prefix = " ".join(words).strip(" -(")
    return prefix if len(prefix) >= 3 else None


def control_status(text: str) -> dict:
    """'12 Días de atraso' / '5 Días de adelanto' / '16 Día adelante' / 'OK' -> estado y días."""
    t = unicodedata.normalize("NFKD", text.lower()).encode("ascii", "ignore").decode()
    m = re.search(r"(\d+)\s*dias?\s*(?:de\s*)?(atraso|adelant)", t)
    if m:
        return {"estado": "atraso" if m.group(2) == "atraso" else "adelanto", "dias": int(m.group(1)), "texto": squash(text)}
    if re.search(r"\bok\b|al dia", t):
        return {"estado": "al_dia", "dias": 0, "texto": "OK"}
    return {"estado": None, "dias": None, "texto": squash(text) or None}


def control_from_rest(rest: str) -> str:
    """Quita del control de programación las etiquetas de la gráfica que el PDF pega al final ('60%', '0%')."""
    m = re.search(r"\d+\s*d[ií]as?\s*(?:de\s*)?(?:atraso|adelant\w*)|\bOK\b", rest, re.IGNORECASE)
    return m.group(0) if m else ""


def parse_chapters_from_text(lines: list[str]) -> list[dict]:
    chapters = []
    for line in lines:
        m = CHAPTER_LINE.match(line)
        if not m:
            continue
        chapters.append({
            "numero": int(m.group("n")),
            "nombre": squash(m.group("nombre")),
            "inicio": to_iso(m.group("inicio")),
            "fin": to_iso(m.group("fin")),
            "avance_anterior": to_number(m.group("anterior")),
            "avance_actual": to_number(m.group("actual")),
            "control": control_status(control_from_rest(m.group("resto"))),
        })
    return chapters


def parse_chapters_from_tables(rows: list[list[str]]) -> list[dict]:
    chapters = []
    for row in rows:
        cells = [squash(c) for c in row]
        if not cells or not re.fullmatch(r"\d{1,2}", cells[0] or ""):
            continue
        dates = [c for c in cells if re.fullmatch(DATE, c)]
        pcts = [c for c in cells if re.fullmatch(PCT, c)]
        name = next((c for c in cells[1:] if c and not re.fullmatch(DATE, c) and not re.fullmatch(PCT, c)), "")
        if len(dates) < 2 or len(pcts) < 2 or not name:
            continue
        control = next((c for c in cells if re.search(r"atraso|adelant|^OK$", c, re.IGNORECASE)), "")
        chapters.append({
            "numero": int(cells[0]),
            "nombre": name,
            "inicio": to_iso(dates[0]),
            "fin": to_iso(dates[1]),
            "avance_anterior": to_number(pcts[0]),
            "avance_actual": to_number(pcts[1]),
            "control": control_status(control),
        })
    return chapters


def parse_budget(rows: list[list[str]], text: str) -> dict:
    budget = {"presupuesto_aprobado": None, "inversion_ejecutada": None, "avance_inversion": None}
    for i, row in enumerate(rows):
        if any("PRESUPUESTO APROBADO" in squash(c) for c in row) and i + 1 < len(rows):
            values = [squash(c) for c in rows[i + 1] if squash(c)]
            money = [v for v in values if "$" in v]
            pct = [v for v in values if "%" in v]
            if len(money) >= 2:
                budget["presupuesto_aprobado"] = to_number(money[0])
                budget["inversion_ejecutada"] = to_number(money[1])
            if pct:
                budget["avance_inversion"] = to_number(pct[0])
            break
    if budget["presupuesto_aprobado"] is None:
        # Respaldo sobre el texto: la línea con dos valores en pesos y un porcentaje
        m = re.search(r"\$\s*([\d\.\s]+,\d+)\s+\$\s*([\d\.\s]+,\d+)\s+(" + PCT + ")", text)
        if m:
            budget = {
                "presupuesto_aprobado": to_number(m.group(1)),
                "inversion_ejecutada": to_number(m.group(2)),
                "avance_inversion": to_number(m.group(3)),
            }
    return budget


def bullets(text: str) -> list[str]:
    """Divide un bloque en viñetas ('* ...'); conserva el título de capítulo de las actividades."""
    text = re.sub(r"[ \t]+", " ", text or "").strip()
    if not text:
        return []
    parts = re.split(r"\s*\*\s+|\n(?=\S)", text)
    items = []
    for part in parts:
        part = squash(part).lstrip("*").strip()
        if part and not is_garbled(part):
            items.append(part)
    return items


def parse_sections(rows: list[list[str]]) -> dict:
    """Comité, actividades de la semana y observaciones: el texto que sigue a cada encabezado hasta el siguiente."""
    sections = {key: [] for key in SECTION_HEADERS}
    current = None
    for row in rows:
        first = (row[0] or "").strip() if row else ""
        upper = squash(first).upper()
        header = next((key for key, title in SECTION_HEADERS.items() if upper.startswith(title)), None)
        if header:
            current = header
            continue
        if any(upper.startswith(stop) for stop in STOP_HEADERS):
            current = None
            continue
        if current and first:
            sections[current].extend(bullets(first))
    return sections


def parse_policies(lines: list[str]) -> list[dict]:
    policies = []
    contractor = None
    for line in lines:
        header = POLICY_HEADER.match(line)
        if header:
            contractor = squash(header.group("contratista"))
            continue
        m = POLICY_LINE.match(line)
        if not m or not contractor:
            continue
        amparo = squash(m.group("amparo"))
        policies.append({
            "contratista": readable_prefix(contractor) if is_garbled(contractor) else contractor,
            "contratista_original": contractor,
            "amparo": None if is_garbled(amparo) else amparo,
            "numero": m.group("numero"),
            "valor_asegurado": to_number(m.group("valor")),
            "inicio": to_iso(m.group("inicio")),
            "fin": to_iso(m.group("fin")),
            "estado": m.group("estado"),
        })
    return policies


def extract_photos(pdf, folder: str | None) -> list[dict]:
    """Registro fotográfico: las fotos son JPEG; el logo del encabezado (PNG) y las franjas del diseño se descartan."""
    photos = []
    for page_index, page in enumerate(pdf.pages):
        for image in page.images:
            if "DCT" not in str(image["stream"].get_filters()):
                continue
            if image["width"] < PHOTO_MIN_POINTS or image["height"] < PHOTO_MIN_POINTS:
                continue
            name = f"foto-{len(photos) + 1:02d}.jpg"
            width = height = None
            if folder:
                bbox = (max(0, image["x0"]), max(0, image["top"]), min(page.width, image["x1"]), min(page.height, image["bottom"]))
                rendered = page.crop(bbox).to_image(resolution=PHOTO_DPI).original.convert("RGB")
                rendered.save(Path(folder) / name, "JPEG", quality=85, optimize=True)
                width, height = rendered.size
            photos.append({"archivo": name, "pagina": page_index + 1, "ancho": width, "alto": height})
    return photos


def parse(path: str, photos_folder: str | None = None) -> dict:
    with pdfplumber.open(path) as pdf:
        page_texts = [p.extract_text() or "" for p in pdf.pages]
        rows: list[list[str]] = []
        for page in pdf.pages:
            for table in page.extract_tables():
                rows.extend([[c or "" for c in r] for r in table])
        photos = extract_photos(pdf, photos_folder)

    text = "\n".join(page_texts)
    lines = [squash(l) for l in text.splitlines() if squash(l)]
    first = page_texts[0] if page_texts else ""

    numero = re.search(r"INFORME SEMANAL No\.?\s*:?\s*(\d+)", first)
    fecha = re.search(r"FECHA DEL INFORME:\s*(.+)", first)
    inicio_fin = re.search(rf"FECHA INICIO\s+({DATE})\s+FECHA FINALIZACI[ÓO]N\s+({DATE})", first)
    interventoria = re.search(r"INTERVENTOR[ÍI]A\s+(.+?)\s+NIT", first)
    proyecto = re.search(r"^PROYECTO\s+(?!:)(.+)$", first, re.MULTILINE)
    constructor = re.search(r"^CONSTRUCTOR\s+(.+?)(?:\s+NIT\s+([\d\.\-]+))?\s*$", first, re.MULTILINE)
    ubicacion = re.search(r"^UBICACI[ÓO]N\s+(.+?)\s+FECHA INICIO", first, re.MULTILINE)
    nit_interventoria = re.search(r"INTERVENTOR[ÍI]A\s+.+?\s+NIT\s+([\d\.\-]+)", first)

    by_text = parse_chapters_from_text(lines)
    by_table = parse_chapters_from_tables(rows)
    chapters = by_text if len(by_text) >= len(by_table) else by_table

    policies = parse_policies(lines)
    sections = parse_sections(rows)

    return {
        "parser_version": PARSER_VERSION,
        "numero": int(numero.group(1)) if numero else None,
        "fecha_texto": squash(fecha.group(1)) if fecha else None,
        "fecha": spanish_date_to_iso(fecha.group(1)) if fecha else None,
        "proyecto": squash(proyecto.group(1)) if proyecto else None,
        "interventoria": squash(interventoria.group(1)) if interventoria else None,
        "interventoria_nit": nit_interventoria.group(1) if nit_interventoria else None,
        "constructor": squash(constructor.group(1)) if constructor else None,
        "constructor_nit": constructor.group(2) if constructor else None,
        "ubicacion": squash(ubicacion.group(1)) if ubicacion else None,
        "fecha_inicio_obra": to_iso(inicio_fin.group(1)) if inicio_fin else None,
        "fecha_fin_obra": to_iso(inicio_fin.group(2)) if inicio_fin else None,
        "presupuesto": parse_budget(rows, text),
        "capitulos": chapters,
        "comite": sections["comite"],
        "actividades": sections["actividades"],
        "observaciones": sections["observaciones"],
        "polizas": policies,
        "fotos": photos,
    }


def main() -> int:
    logging.disable(logging.CRITICAL)  # pdfminer avisa en stderr de colores inválidos del PDF; no afecta el texto
    args = sys.argv[1:]
    photos_folder = None
    if "--fotos" in args:
        i = args.index("--fotos")
        photos_folder = args[i + 1] if i + 1 < len(args) else None
        args = args[:i] + args[i + 2:]
    if len(args) != 1 or (photos_folder is not None and not Path(photos_folder).is_dir()):
        print("Uso: parse_informe.py <informe.pdf> [--fotos <carpeta existente>]", file=sys.stderr)
        return 2
    try:
        data = parse(args[0], photos_folder)
    except Exception as exc:  # noqa: BLE001 - se informa a Laravel como error legible
        print(json.dumps({"error": f"{type(exc).__name__}: {exc}"}, ensure_ascii=False))
        return 1
    print(json.dumps(data, ensure_ascii=False))
    return 0 if data.get("numero") else 1


if __name__ == "__main__":
    sys.exit(main())
