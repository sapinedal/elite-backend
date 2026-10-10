#!/usr/bin/env python3
"""
Extractor JSON de tareas desde el Excel "Dashboard Seguimiento Inverconstruccion.xlsx"
Hoja: Tareas
"""

import openpyxl
import re
import json
import sys
from datetime import datetime, date

EXCEL_PATH = 'excels/Dashboard Seguimiento Inverconstruccion.xlsx'
SHEET_NAME = 'Tareas'

MONTHS_ES = {
    'enero': 1, 'febrero': 2, 'marzo': 3, 'abril': 4,
    'mayo': 5, 'junio': 6, 'juniio': 6,
    'julio': 7, 'agosto': 8,
    'septiembre': 9, 'octubre': 10, 'cotubre': 10,
    'noviembre': 11, 'diciembre': 12, 'dicicembre': 12, 'diciciembre': 12
}

def clean_text(s):
    if not s:
        return ''
    s = str(s).strip().lower()
    s = re.sub(r'\s+', ' ', s)
    s = s.replace('d e ', 'de ')
    return s

def to_date_str(val):
    if val is None:
        return None
    if isinstance(val, (datetime, date)):
        return val.strftime('%Y-%m-%d')
    if isinstance(val, str):
        val = val.strip()
        m = re.match(r'^(\d{4})-(\d{1,2})-(\d{1,2})', val)
        if m:
            y, mo, d = int(m.group(1)), int(m.group(2)), int(m.group(3))
            if mo > 12 and d <= 12:
                mo, d = d, mo
            if mo > 12:
                mo = 7  # fallback if typo like 17
            try:
                return date(y, mo, d).strftime('%Y-%m-%d')
            except Exception:
                return f'{y:04d}-07-01'
        m = re.match(r'^(\d{1,2})[/-](\d{1,2})[/-](\d{4})', val)
        if m:
            d, mo, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
            if mo > 12 and d <= 12:
                mo, d = d, mo
            if mo > 12:
                mo = 7  # fallback if typo like 17
            try:
                return date(y, mo, d).strftime('%Y-%m-%d')
            except Exception:
                return f'{y:04d}-07-01'
    return None

def parse_header_date(h: str, fallback_year: int = 2025):
    s = clean_text(h)
    if not s or s.startswith('columna'):
        return None, None
    
    # 1. DD/MM/YYYY o DD-MM-YYYY
    m = re.search(r'(\d{1,2})[/-](\d{1,2})[/-](\d{4})', s)
    if m:
        y = int(m.group(3))
        if y > 2026:
            y = 2026
        return f'{y:04d}-{int(m.group(2)):02d}-{int(m.group(1)):02d}', y
        
    # 2. DD-MM/YYYY o DD/MM/YY o DD-MM-YY
    m = re.search(r'(\d{1,2})[/-](\d{1,2})[/-](\d{2})$', s)
    if m:
        y = int('20' + m.group(3))
        if y > 2026:
            y = 2026
        return f'{y:04d}-{int(m.group(2)):02d}-{int(m.group(1)):02d}', y

    # 3. Mes en español
    for mname, mnum in MONTHS_ES.items():
        if mname in s:
            # ej. 'julio 27-26'
            m1 = re.search(rf'{mname}\s*(\d{{1,2}})[-/](\d{{2,4}})', s)
            if m1:
                d = int(m1.group(1))
                y = int(m1.group(2))
                if y < 100:
                    y += 2000
                if y > 2026:
                    y = 2026
                return f'{y:04d}-{mnum:02d}-{d:02d}', y
            
            # ej. 'enero 14 2026', 'enero 19 de 2026'
            m2 = re.search(rf'{mname}\s*(\d{{1,2}})(?:\s*(?:de\s*)?(\d{{4}}))?', s)
            if m2 and m2.group(1):
                d = int(m2.group(1))
                y = int(m2.group(2)) if m2.group(2) else fallback_year
                if y > 2026:
                    y = 2026
                return f'{y:04d}-{mnum:02d}-{d:02d}', y

            # ej. '14 de abril 2025', '6 de mayo', '28 de enero 2026'
            m3 = re.search(r'(\d{1,2})\s*(?:de\s*)?' + mname + r'(?:\s*(?:de\s*)?(\d{4}))?', s)
            if m3:
                d = int(m3.group(1))
                y = int(m3.group(2)) if m3.group(2) else fallback_year
                if y > 2026:
                    y = 2026
                return f'{y:04d}-{mnum:02d}-{d:02d}', y
            
            # Sin día ej. 'comite juridico-comercial abril 2025'
            m4 = re.search(r'(?:abril|mayo|junio|julio|agosto|septiembre|octubre|cotubre|noviembre|diciembre|dicicembre|diciciembre|enero|febrero)\s*(\d{4})?', s)
            if m4:
                y = int(m4.group(1)) if m4.group(1) else fallback_year
                if y > 2026:
                    y = 2026
                return f'{y:04d}-{mnum:02d}-01', y

    return None, None

def extract(single_row=None):
    wb = openpyxl.load_workbook(EXCEL_PATH, data_only=True)
    if SHEET_NAME not in wb.sheetnames:
        raise ValueError(f'Hoja {SHEET_NAME} no encontrada en {EXCEL_PATH}')
        
    sheet = wb[SHEET_NAME]

    column_dates = {}
    current_year = 2025
    for c in range(10, sheet.max_column + 1):
        header_val = sheet.cell(1, c).value
        if header_val:
            dt, yr = parse_header_date(str(header_val), fallback_year=current_year)
            if yr:
                current_year = yr
            if dt:
                column_dates[c] = (str(header_val).strip(), dt)

    rows = []
    if single_row is not None:
        target_rows = [single_row]
    else:
        target_rows = list(range(2, sheet.max_row + 1))

    for r in target_rows:
        title_raw = sheet.cell(r, 1).value
        if not title_raw or str(title_raw).strip() == '':
            continue

        obs = []
        for c in range(10, sheet.max_column + 1):
            cell_val = sheet.cell(r, c).value
            if cell_val is not None and str(cell_val).strip() != '':
                col_info = column_dates.get(c)
                obs.append({
                    'col': c,
                    'date': col_info[1] if col_info else None,
                    'header': col_info[0] if col_info else f'Columna {c}',
                    'text': str(cell_val).strip()
                })

        rows.append({
            'row': r,
            'title': str(title_raw).strip(),
            'priority': str(sheet.cell(r, 2).value or 'P2').strip(),
            'directriz': sheet.cell(r, 3).value,
            'responsable': sheet.cell(r, 4).value,
            'estado': sheet.cell(r, 5).value,
            'area': sheet.cell(r, 6).value,
            'start_date': to_date_str(sheet.cell(r, 7).value),
            'sched_end_date': to_date_str(sheet.cell(r, 8).value),
            'actual_end_date': to_date_str(sheet.cell(r, 9).value),
            'observations': obs
        })

    return rows

if __name__ == '__main__':
    single_row_arg = None
    if len(sys.argv) > 1:
        if sys.argv[1] == '--test':
            single_row_arg = 3
        elif sys.argv[1] == '--row' and len(sys.argv) > 2:
            single_row_arg = int(sys.argv[2])

    data = extract(single_row_arg)
    print(json.dumps(data, ensure_ascii=False))
