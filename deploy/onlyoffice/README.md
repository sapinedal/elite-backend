# Editor en el navegador (ONLYOFFICE Docs)

El módulo Documental abre hojas de cálculo, documentos y presentaciones en el navegador, con edición
simultánea de varias personas. El editor es **ONLYOFFICE Docs Community** (gratis hasta 20 conexiones de
edición simultáneas) y corre en Docker en el mismo servidor de la app.

## Cómo funciona

1. El usuario abre un archivo: la API valida sus permisos de carpeta y proyecto y devuelve la configuración
   del editor firmada con JWT, con una URL temporal de Spaces para que ONLYOFFICE descargue el archivo.
2. Quienes abren el mismo archivo editan juntos (misma sesión) y ven los cambios en vivo.
3. Al cerrar el último editor (o al usar *Guardar*), ONLYOFFICE llama al callback firmado de la API, que
   guarda la versión anterior en la carpeta oculta `.versiones/` y sube la nueva a Spaces, con registro en
   auditoría (acción `edit`).

- Se editan `.xlsx`, `.docx` y `.pptx`. Los formatos antiguos (`.xls`, `.doc`, `.ppt`, `.csv`, `.odt`…) y PDF
  se abren en modo lectura; desde *Archivo › Guardar como* se puede guardar una copia moderna.
- Se conservan las últimas `ONLYOFFICE_KEEP_VERSIONS` versiones (20 por defecto) de cada archivo.

## Instalación (una vez)

1. Crear el registro DNS **A** `docs.inverconstruccion.com` → IP del servidor.
2. Copiar esta carpeta al servidor y ejecutar como root:

   ```bash
   bash deploy/onlyoffice/install.sh
   ```

   Instala Docker, levanta el contenedor, configura nginx + HTTPS (certbot) y agrega
   `ONLYOFFICE_URL` y `ONLYOFFICE_JWT_SECRET` al `.env` de la API.

Sin `ONLYOFFICE_URL` el editor queda deshabilitado y la app sigue funcionando como antes (vista previa y descarga).

## Operación

- Estado: `curl https://docs.inverconstruccion.com/healthcheck` → `true`
- Logs: `docker logs onlyoffice --tail 100`
- Reiniciar: `cd /opt/onlyoffice && docker compose restart`
- Actualizar: cambiar la versión de la imagen en `docker-compose.yml`, `docker compose pull && docker compose up -d`
- El secreto JWT vive en `/opt/onlyoffice/.env` y debe ser el mismo que `ONLYOFFICE_JWT_SECRET` de la API.
