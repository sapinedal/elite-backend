"""
Importador de tareas históricas desde Excel a PostgreSQL para Elite.
Excel: excels/Dashboard Seguimiento Inverconstruccion.xlsx (Hoja: Tareas)

Uso:
  python3 scripts/import_tasks.py --test            # Importa solo la tarea de prueba (Fila 3: Protocolización CTO T1)
  python3 scripts/import_tasks.py --row <N>          # Importa una fila específica
  python3 scripts/import_tasks.py --dry-run          # Simula la importación completa sin modificar la BD
  python3 scripts/import_tasks.py --all              # Importa todas las tareas del Excel
"""

import openpyxl
import psycopg2
import re
import sys
from datetime import datetime, date

# ─────────────────────────────────────────────────────────────
# 0. Configuración
# ─────────────────────────────────────────────────────────────
EXCEL_PATH = 'excels/Dashboard Seguimiento Inverconstruccion.xlsx'
SHEET_NAME = 'Tareas'
ADMIN_USER_ID = 5           # Samuel Pineda (Administrador por defecto para requested_by_id)
DAILY_COLS_START = 10       # Primera columna con observaciones de dailys/comités

MONTHS_ES = {
    'enero': 1, 'febrero': 2, 'marzo': 3, 'abril': 4,
    'mayo': 5, 'junio': 6, 'juniio': 6,
    'julio': 7, 'agosto': 8,
    'septiembre': 9, 'octubre': 10, 'cotubre': 10,
    'noviembre': 11, 'diciembre': 12, 'dicicembre': 12, 'diciciembre': 12
}

STATUS_MAP = {
    'por hacer': 'Por hacer',
    'en espera': 'En espera',
    'en progreso': 'En progreso',
    'en proceso': 'En progreso',
    'completada': 'Completada',
    'completado': 'Completada',
    'finalizada': 'Completada',
    'finalizado': 'Completada',
}

PRIORITY_VALID = {'P0', 'P1', 'P2', 'P3'}

# ─────────────────────────────────────────────────────────────
# 1. Leer .env y conectar a PostgreSQL
# ─────────────────────────────────────────────────────────────
env_vars = {}
try:
    with open('.env', 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                k, v = line.split('=', 1)
                env_vars[k.strip()] = v.strip().strip("'\"")
except Exception as e:
    print(f'Error leyendo .env: {e}')
    sys.exit(1)

try:
    conn = psycopg2.connect(
        host=env_vars.get('DB_HOST', '127.0.0.1'),
        port=env_vars.get('DB_PORT', '5432'),
        database=env_vars.get('DB_DATABASE', 'elite'),
        user=env_vars.get('DB_USERNAME', 'postgres'),
        password=env_vars.get('DB_PASSWORD', '')
    )
    cur = conn.cursor()
    print(f"✓ Conectado a base de datos: {env_vars.get('DB_HOST')}/{env_vars.get('DB_DATABASE')}")
except Exception as e:
    print(f'✗ Error conectando a BD: {e}')
    sys.exit(1)


# ─────────────────────────────────────────────────────────────
# 2. Helpers y Mapeos
# ─────────────────────────────────────────────────────────────
def clean_text(s):
    if not s:
        return ''
    s = str(s).strip().lower()
    s = re.sub(r'\s+', ' ', s)
    s = s.replace('d e ', 'de ')
    return s

def norm(s: str) -> str:
    """Normaliza texto: mayúsculas, sin espacios dobles ni tildes básicas para match flexible."""
    if not s:
        return ''
    s = str(s).strip().upper()
    s = re.sub(r'\s+', ' ', s)
    return s

def to_date(val):
    if val is None:
        return None
    if isinstance(val, datetime):
        return val.date()
    if isinstance(val, date):
        return val
    if isinstance(val, str):
        val = val.strip()
        # Intentar parsear YYYY-MM-DD o DD/MM/YYYY
        m = re.match(r'^(\d{4})-(\d{1,2})-(\d{1,2})', val)
        if m:
            return date(int(m.group(1)), int(m.group(2)), int(m.group(3)))
        m = re.match(r'^(\d{1,2})[/-](\d{1,2})[/-](\d{4})', val)
        if m:
            return date(int(m.group(3)), int(m.group(2)), int(m.group(1)))
    return None

def parse_header_date(h: str, fallback_year: int = 2025):
    s = clean_text(h)
    if not s or s.startswith('columna'):
        return None, None
    
    # 1. DD/MM/YYYY or DD-MM-YYYY
    m = re.search(r'(\d{1,2})[/-](\d{1,2})[/-](\d{4})', s)
    if m:
        d, mo, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        return date(y, mo, d), y
        
    # 2. DD-MM/YYYY or DD/MM/YY or DD-MM-YY
    m = re.search(r'(\d{1,2})[/-](\d{1,2})[/-](\d{2})$', s)
    if m:
        d, mo = int(m.group(1)), int(m.group(2))
        y = int('20' + m.group(3))
        return date(y, mo, d), y

    # 3. Texto con nombre de mes
    for mname, mnum in MONTHS_ES.items():
        if mname in s:
            # Patrón: 'mes DD-YY' o 'mes DD/YY' ej. 'julio 27-26'
            m1 = re.search(rf'{mname}\s*(\d{{1,2}})[-/](\d{{2,4}})', s)
            if m1:
                d = int(m1.group(1))
                y = int(m1.group(2))
                if y < 100:
                    y += 2000
                return date(y, mnum, d), y
            
            # Patrón: 'mes DD YYYY' ej. 'enero 14 2026', 'enero 19 de 2026'
            m2 = re.search(rf'{mname}\s*(\d{{1,2}})(?:\s*(?:de\s*)?(\d{{4}}))?', s)
            if m2 and m2.group(1):
                d = int(m2.group(1))
                y = int(m2.group(2)) if m2.group(2) else fallback_year
                return date(y, mnum, d), y

            # Patrón: 'DD [de] mes [de] [YYYY]' ej. '14 de abril 2025', '6 de mayo', '28 de enero 2026'
            m3 = re.search(r'(\d{1,2})\s*(?:de\s*)?' + mname + r'(?:\s*(?:de\s*)?(\d{4}))?', s)
            if m3:
                d = int(m3.group(1))
                y = int(m3.group(2)) if m3.group(2) else fallback_year
                return date(y, mnum, d), y
            
            # Patrón sin día explícito (asume día 1) ej. 'comite juridico-comercial abril 2025'
            m4 = re.search(r'(?:abril|mayo|junio|julio|agosto|septiembre|octubre|cotubre|noviembre|diciembre|dicicembre|diciciembre|enero|febrero)\s*(\d{4})?', s)
            if m4:
                y = int(m4.group(1)) if m4.group(1) else fallback_year
                return date(y, mnum, 1), y

    return None, None


# ─────────────────────────────────────────────────────────────
# 3. Cargar Usuarios y Áreas de BD
# ─────────────────────────────────────────────────────────────
user_cache = {}
cur.execute('SELECT id, name, email FROM users')
for uid, uname, uemail in cur.fetchall():
    if uemail:
        user_cache[norm(uemail)] = uid
        user_cache[uemail.strip().lower()] = uid
    if uname:
        user_cache[norm(uname)] = uid

# Mapeos estáticos conocidos para coincidencias comunes
STATIC_USER_ALIASES = {
    'SARA': 1,
    'SARA MORENO': 1,
    'SARA ELENA MORENO OROZCO': 1,
    'INGRID': 2,
    'INGRID OSPINO': 2,
    'INGRID PAOLA OSPICIO PACHECO': 2,
    'PAOLA ANDREA ARENAS GAVIRIA': 3,
    'NATALIA ANDREA POSADA RAVE': 4,
    'SAMUEL PINEDA': 5,
    'LÍDER TRÁMITES Y ESCRITURACIÓN': 6,
    'TRÁMITES CIUDADELA SAN MIGUEL': 6,
    'TRAMITES CIUDADELA SAN MIGUEL': 6,
    'JUAN CARLOS ESQUIVEL HOYOS': 8,
    'SANTIAGO PRIETO PINTO': 9,
    'JORGE ELIAS PEMBERTY ZAPATA': 10,
    'OBRA SAN MIGUEL': 10,
    'JULIÁN POSADA': 11,
    'JULIAN POSADA': 11,
    'JULIAN ANDRES POSADA MORALES': 11,
    'SOFIA YEPES PEÑA': 12,
    'SOFIA YEPES': 12,
    'ISABEL CRISTINA GARCIA MARIN': 13,
    'CLAUDIA PATRICIA JIMENEZ CARVAJAL': 14,
    'SHARON JOLAINE VELANDIA TELLEZ': 15,
    'VANESSA CALLE VALDERRAMA': 16,
    'ANALISTACONTABLE@INVERCONSTRUCCION.COM': 16,
    'GINNA MARCELA QUINTANA LEON': 17,
    'MARCELA QUINTANA': 17,
    'MANUELA MARIA MEJIA GOMEZ': 18,
    'MANUELA MEJIA': 18,
    'MMEJIA@CYBPROJECT.COM': 18,
    'SANTIAGO SANCHEZ VILLA': 19,
    'SANTIAGO SANCHEZ': 19,
    'SSANCHEZ@CYBPROJECT.COM': 19,
}
for k, v in STATIC_USER_ALIASES.items():
    user_cache[norm(k)] = v

def resolve_user_id(raw) -> int | None:
    if not raw:
        return None
    s = str(raw).strip()
    # 1. Direct key
    if norm(s) in user_cache:
        return user_cache[norm(s)]
    if s.lower() in user_cache:
        return user_cache[s.lower()]
    # 2. Substring match
    for k, uid in user_cache.items():
        if len(k) > 4 and (k in norm(s) or norm(s) in k):
            return uid
    return None

area_cache = {}
cur.execute('SELECT id, name FROM areas')
for aid, aname in cur.fetchall():
    if aname:
        area_cache[norm(aname)] = aid

STATIC_AREA_ALIASES = {
    'COMERCIAL': 1,
    'OPERACIONES': 2,
    'TECNOLOGÍA': 3,
    'TECNOLOGIA': 3,
    'ADMINISTRATIVO': 4,
    'TRÁMITES Y ESCRITURACIÓN': 5,
    'TRAMITES Y ESCRITURACION': 5,
    'ESCRITURACIÓN': 5,
    'ESCRITURACION': 5,
    'PROCESOS Y GESTIÓN DOCUMENTAL': 6,
    'TÉCNICA': 7,
    'TECNICA': 7,
    'CONTABLE': 16,
    'CONTABILIDAD': 16,
    'JURÍDICA': 21,
    'JURIDICA': 21,
    'MERCADEO': 22,
    'MARKETING': 22,
}
for k, v in STATIC_AREA_ALIASES.items():
    area_cache[norm(k)] = v

def resolve_or_create_area(raw, dry_run=False) -> int | None:
    if not raw:
        return None
    s = str(raw).strip()
    k = norm(s)
    if k in area_cache:
        return area_cache[k]
    
    # Substring match
    for ak, aid in area_cache.items():
        if len(ak) > 3 and (ak in k or k in ak):
            return aid

    if dry_run:
        return 999  # Fake ID for dry run

    # Crear área si no existe
    cur.execute('INSERT INTO areas (name, created_at, updated_at) VALUES (%s, NOW(), NOW()) RETURNING id', (s,))
    new_id = cur.fetchone()[0]
    area_cache[k] = new_id
    print(f'  [ÁREA CREADA] "{s}" → ID: {new_id}')
    return new_id


# ─────────────────────────────────────────────────────────────
# 4. Procesamiento del Excel
# ─────────────────────────────────────────────────────────────
def run_import(single_row: int | None = None, dry_run: bool = False):
    print(f'\n=== CARGANDO EXCEL ({EXCEL_PATH}) ===')
    wb = openpyxl.load_workbook(EXCEL_PATH, data_only=True)
    if SHEET_NAME not in wb.sheetnames:
        print(f'✗ Error: No se encontró la hoja "{SHEET_NAME}". Hojas disponibles: {wb.sheetnames}')
        sys.exit(1)
        
    sheet = wb[SHEET_NAME]
    print(f'✓ Hoja seleccionada: "{SHEET_NAME}" | Filas: {sheet.max_row} | Columnas: {sheet.max_column}')

    # Parsear cabeceras de columnas de observaciones
    column_dates = {}
    current_year = 2025
    for c in range(DAILY_COLS_START, sheet.max_column + 1):
        header_val = sheet.cell(1, c).value
        if header_val:
            parsed_dt, yr = parse_header_date(str(header_val), fallback_year=current_year)
            if yr:
                current_year = yr
            if parsed_dt:
                column_dates[c] = (str(header_val).strip(), parsed_dt)

    print(f'✓ {len(column_dates)} columnas de Dailys/Comités identificadas con fechas válidas.')

    # Determinar rango de filas
    if single_row is not None:
        row_indices = [single_row]
        print(f'\n=== MODO FILA ÚNICA: Fila {single_row} ===')
    else:
        row_indices = list(range(2, sheet.max_row + 1))
        print(f'\n=== MODO MASIVO: {len(row_indices)} filas a evaluar ===')

    if dry_run:
        print('⚠️  [MODO DRY-RUN ACTIVADO] No se realizarán escrituras permanentes en la base de datos.')

    tasks_imported = 0
    obs_imported = 0
    skipped_empty = 0

    for r in row_indices:
        title_raw = sheet.cell(r, 1).value
        if not title_raw or str(title_raw).strip() == '':
            skipped_empty += 1
            continue

        title = str(title_raw).strip()
        priority_raw = str(sheet.cell(r, 2).value or 'P2').strip().upper()
        priority = priority_raw if priority_raw in PRIORITY_VALID else 'P2'

        directriz = sheet.cell(r, 3).value
        responsable_raw = sheet.cell(r, 4).value
        estado_raw = str(sheet.cell(r, 5).value or 'Por hacer').strip().lower()
        status = STATUS_MAP.get(estado_raw, 'Por hacer')

        area_raw = sheet.cell(r, 6).value
        start_date = to_date(sheet.cell(r, 7).value)
        sched_end_date = to_date(sheet.cell(r, 8).value)
        actual_end_date = to_date(sheet.cell(r, 9).value)

        # Reglas de negocio acordadas:
        # 1. requested_by_id = default admin (Samuel Pineda)
        requested_by_id = ADMIN_USER_ID
        
        # 2. responsible_id = buscar usuario; si no existe colocar NULL para poder filtrar
        responsible_id = resolve_user_id(responsable_raw)

        # 3. area_id
        area_id = resolve_or_create_area(area_raw, dry_run=dry_run)

        # 4. Si estado es completada y no hay fecha real, aproximar con fecha estimada
        if status == 'Completada' and actual_end_date is None:
            actual_end_date = sched_end_date

        # Extraer observaciones de esta fila
        row_observations = []
        for c in range(DAILY_COLS_START, sheet.max_column + 1):
            cell_val = sheet.cell(r, c).value
            if cell_val is not None and str(cell_val).strip() != '':
                col_info = column_dates.get(c)
                col_header = col_info[0] if col_info else f'Columna {c}'
                obs_date = col_info[1] if col_info else date.today()
                row_observations.append({
                    'col': c,
                    'header': col_header,
                    'date': obs_date,
                    'text': str(cell_val).strip()
                })

        print(f'\n--- [Fila {r}] ---')
        print(f'  Título:        "{title}"')
        print(f'  Prioridad:     {priority}')
        print(f'  Estado:        {status} (Original: {sheet.cell(r, 5).value})')
        print(f'  Responsable:   {responsable_raw} → ID: {responsible_id} ({"Sin asignar" if responsible_id is None else "Asignado"})')
        print(f'  Solicitado por: ID {requested_by_id} (Admin Samuel Pineda)')
        print(f'  Área:          {area_raw} → ID: {area_id}')
        print(f'  Fechas:        Inicio={start_date} | Prog={sched_end_date} | Real={actual_end_date}')
        print(f'  Observaciones: {len(row_observations)} notas históricas encontradas')

        for idx, o in enumerate(row_observations, 1):
            print(f'    [{idx}] {o["date"]} ({o["header"]}): {o["text"][:60]}...')

        if not dry_run:
            # Insertar en tabla tasks
            cur.execute("""
                INSERT INTO tasks (
                    title, priority, status, requested_by_id, responsible_id, area_id,
                    start_date, scheduled_end_date, actual_end_date, created_at, updated_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                RETURNING id
            """, (
                title, priority, status, requested_by_id, responsible_id, area_id,
                start_date, sched_end_date, actual_end_date
            ))
            task_id = cur.fetchone()[0]
            tasks_imported += 1

            # Insertar audit log de creación
            cur.execute("""
                INSERT INTO task_audit_logs (task_id, user_id, action, changes, created_at, updated_at)
                VALUES (%s, %s, 'created', %s, NOW(), NOW())
            """, (
                task_id, requested_by_id, psycopg2.extras.Json({
                    'title': {'new': title},
                    'priority': {'new': priority},
                    'status': {'new': status},
                    'responsible_id': {'new': responsible_id},
                    'area_id': {'new': area_id}
                })
            ))

            # Insertar observaciones históricas
            # El autor de las observaciones es el responsable de la tarea (o fallback a admin si es null)
            observer_user_id = responsible_id if responsible_id is not None else ADMIN_USER_ID

            for o in row_observations:
                obs_dt = datetime(o['date'].year, o['date'].month, o['date'].day, 12, 0, 0)
                cur.execute("""
                    INSERT INTO task_observations (task_id, user_id, observation, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s)
                """, (
                    task_id, observer_user_id, o['text'], obs_dt, obs_dt
                ))
                obs_imported += 1

            print(f'  ✓ Insertada exitosamente en BD con Task ID: {task_id}')
        else:
            tasks_imported += 1
            obs_imported += len(row_observations)

    if not dry_run:
        conn.commit()
        print(f'\n==================================================')
        print(f'✓ IMPORTACIÓN EXITOSA Y CONFIRMADA EN BD')
        print(f'  Tareas creadas:        {tasks_imported}')
        print(f'  Observaciones creadas: {obs_imported}')
        print(f'==================================================\n')
    else:
        print(f'\n==================================================')
        print(f'✓ SIMULACIÓN (DRY-RUN) FINALIZADA')
        print(f'  Tareas a crear:        {tasks_imported}')
        print(f'  Observaciones a crear: {obs_imported}')
        print(f'==================================================\n')

# ─────────────────────────────────────────────────────────────
# 5. Entrada principal
# ─────────────────────────────────────────────────────────────
if __name__ == '__main__':
    try:
        import psycopg2.extras
        if '--test' in sys.argv:
            # Fila 3: 'protocolizacion certificado tecnico de ocupación T1' (En progreso)
            run_import(single_row=3, dry_run=False)
        elif '--row' in sys.argv:
            idx = sys.argv.index('--row')
            row_num = int(sys.argv[idx + 1])
            run_import(single_row=row_num, dry_run=False)
        elif '--dry-run' in sys.argv:
            run_import(single_row=None, dry_run=True)
        elif '--all' in sys.argv:
            run_import(single_row=None, dry_run=False)
        else:
            print("Uso:")
            print("  python3 scripts/import_tasks.py --test      # Importa Fila 3 (Prueba)")
            print("  python3 scripts/import_tasks.py --row <N>   # Importa una fila específica")
            print("  python3 scripts/import_tasks.py --dry-run   # Simula todo")
            print("  python3 scripts/import_tasks.py --all       # Importa todo")
    except Exception as e:
        conn.rollback()
        print(f'\n✗ Error durante la ejecución: {e}')
        import traceback
        traceback.print_exc()
    finally:
        cur.close()
        conn.close()
