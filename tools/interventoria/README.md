# Lector de informes semanales de interventoría

`parse_informe.py` lee los PDF "INFORME DE INTERVENTORÍA SEMANA N.pdf" (formato EQP-F-IS de Equiproyectos)
y devuelve un JSON con presupuesto, capítulos (avance y control de programación), comité, actividades,
observaciones y pólizas. Laravel lo ejecuta desde `php artisan interventoria:importar` (programado cada hora).

## Instalación en el servidor (una sola vez)

```bash
sudo python3 -m venv /opt/elite-pdf/venv
sudo /opt/elite-pdf/venv/bin/pip install -r /var/www/html/elite-backend/tools/interventoria/requirements.txt
```

La ruta del intérprete se cambia con `INTERVENTORIA_PYTHON` en el `.env` (por defecto `/opt/elite-pdf/venv/bin/python`).

## Uso

```bash
php artisan interventoria:importar           # lee el último informe si es nuevo o cambió
php artisan interventoria:importar --todos   # importa todo el historial
php artisan interventoria:importar --forzar  # vuelve a leer aunque no haya cambios
/opt/elite-pdf/venv/bin/python tools/interventoria/parse_informe.py informe.pdf   # probar un PDF
```
