#!/usr/bin/env bash
# Instala ONLYOFFICE Docs en este servidor (Ubuntu 24.04) para el editor del módulo Documental.
# Requisito previo: el registro DNS A de docs.inverconstruccion.com apunta a este servidor.
# Uso (como root, desde esta carpeta):  bash install.sh
set -euo pipefail

DOMAIN="docs.inverconstruccion.com"
APP_DIR="/var/www/html/elite-backend"
HERE="$(cd "$(dirname "$0")" && pwd)"
STACK_DIR="/opt/onlyoffice"

echo "==> Verificando DNS de ${DOMAIN} (contra un DNS público: el del servidor puede tener caché vieja)"
SERVER_IP="$(curl -s https://api.ipify.org)"
DNS_IP="$(dig +short A "${DOMAIN}" @1.1.1.1 | tail -1)"
if [ "${DNS_IP}" != "${SERVER_IP}" ]; then
  echo "El DNS de ${DOMAIN} apunta a '${DNS_IP:-nada}' y este servidor es ${SERVER_IP}. Crea el registro A y vuelve a intentar."
  exit 1
fi

echo "==> Instalando Docker"
if ! command -v docker >/dev/null; then
  apt-get update -y
  apt-get install -y docker.io docker-compose-v2
  systemctl enable --now docker
fi

echo "==> Preparando ${STACK_DIR}"
mkdir -p "${STACK_DIR}"
cp "${HERE}/docker-compose.yml" "${STACK_DIR}/docker-compose.yml"
if [ ! -f "${STACK_DIR}/.env" ]; then
  echo "ONLYOFFICE_JWT_SECRET=$(openssl rand -hex 32)" > "${STACK_DIR}/.env"
  chmod 600 "${STACK_DIR}/.env"
fi
# shellcheck disable=SC1091
source "${STACK_DIR}/.env"

echo "==> Levantando ONLYOFFICE (la primera vez tarda unos minutos)"
(cd "${STACK_DIR}" && docker compose up -d)
for i in $(seq 1 60); do
  if curl -fsS http://127.0.0.1:8085/healthcheck 2>/dev/null | grep -q true; then break; fi
  sleep 5
done
curl -fsS http://127.0.0.1:8085/healthcheck | grep -q true || { echo "ONLYOFFICE no respondió; revisa: docker logs onlyoffice"; exit 1; }

echo "==> ${DOMAIN} resuelve a este mismo servidor (la API descarga los archivos editados sin salir a internet)"
grep -q " ${DOMAIN}$" /etc/hosts || echo "127.0.0.1 ${DOMAIN}" >> /etc/hosts

echo "==> Verificando certbot con el plugin de nginx"
certbot plugins 2>/dev/null | grep -q nginx || apt-get install -y python3-certbot-nginx

echo "==> Configurando nginx y HTTPS"
cp "${HERE}/nginx-docs.conf" "/etc/nginx/sites-available/${DOMAIN}"
ln -sf "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
nginx -t && systemctl reload nginx
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos --redirect --register-unsafely-without-email

echo "==> Configurando la API (${APP_DIR}/.env)"
cp "${APP_DIR}/.env" "${APP_DIR}/.env.bak.$(date +%Y%m%d%H%M%S)"
sed -i '/^ONLYOFFICE_URL=/d;/^ONLYOFFICE_JWT_SECRET=/d' "${APP_DIR}/.env"
{
  echo ""
  echo "ONLYOFFICE_URL=https://${DOMAIN}"
  echo "ONLYOFFICE_JWT_SECRET=${ONLYOFFICE_JWT_SECRET}"
} >> "${APP_DIR}/.env"
(cd "${APP_DIR}" && php artisan config:clear)

echo "==> Listo: https://${DOMAIN}/healthcheck"
curl -fsS "https://${DOMAIN}/healthcheck"; echo
