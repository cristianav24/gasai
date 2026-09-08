#!/usr/bin/env bash
#
# Instalador de GasAI en un VPS Ubuntu para tandix.app.
# Ejecutar en el servidor como un usuario con sudo:
#
#   sudo apt update && sudo apt install -y git
#   git clone https://github.com/cristianav24/gasai.git /tmp/gasai-src
#   bash /tmp/gasai-src/docs/deploy/bootstrap.sh
#
# Es idempotente en lo posible: puedes volver a correrlo si algo falla.
set -euo pipefail

DOMAIN="tandix.app"
EMAIL="francia24vs@gmail.com"     # para el certificado TLS
APP_DIR="/var/www/gasai"
REPO="https://github.com/cristianav24/gasai.git"

echo "==================================================================="
echo " Instalando GasAI en ${DOMAIN}  ->  ${APP_DIR}"
echo "==================================================================="

# --- Secretos (no se muestran / no salen a ningún lado) ---
read -rsp "Pega tu DEEPSEEK_API_KEY y presiona Enter: " DEEPSEEK_KEY; echo
read -rp  "Elige un WHATSAPP_VERIFY_TOKEN (texto que también pondrás en Meta): " WA_TOKEN
DB_PASS="$(openssl rand -hex 16)"

# --- 1. Paquetes del sistema ---
echo "==> [1/8] Paquetes del sistema"
sudo apt update
sudo apt install -y nginx postgresql redis-server supervisor unzip git curl \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd \
  php8.3-pcntl php8.3-posix

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
fi
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  sudo apt install -y nodejs
fi

# --- 2. Base de datos ---
echo "==> [2/8] Base de datos PostgreSQL"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='gasai'" | grep -q 1 || \
  sudo -u postgres psql -c "CREATE DATABASE gasai;"
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='gasai'" | grep -q 1 || \
  sudo -u postgres psql -c "CREATE USER gasai WITH ENCRYPTED PASSWORD '${DB_PASS}';"
sudo -u postgres psql -c "ALTER USER gasai WITH ENCRYPTED PASSWORD '${DB_PASS}';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE gasai TO gasai;"
sudo -u postgres psql -d gasai -c "GRANT ALL ON SCHEMA public TO gasai;"

# --- 3. Código ---
echo "==> [3/8] Código"
sudo mkdir -p "${APP_DIR}"
sudo chown -R "$USER":"$USER" "${APP_DIR}"
if [ -d "${APP_DIR}/.git" ]; then
  git -C "${APP_DIR}" pull --ff-only
else
  git clone "${REPO}" "${APP_DIR}"
fi
cd "${APP_DIR}"

# --- 4. Dependencias ---
echo "==> [4/8] Dependencias (composer + assets)"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# --- 5. Configuración (.env) ---
echo "==> [5/8] Configuración"
if [ ! -f .env ]; then
  cp docs/deploy/.env.production.example .env
fi
sed -i "s|<CONTRASEÑA_FUERTE_DB>|${DB_PASS}|g" .env
sed -i "s|<TU_API_KEY_DEEPSEEK>|${DEEPSEEK_KEY}|g" .env
sed -i "s|<TOKEN_QUE_ELIJAS_Y_PEGUES_EN_META>|${WA_TOKEN}|g" .env
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link || true
php artisan optimize
php artisan filament:optimize
sudo chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# --- 6. Nginx + HTTPS ---
echo "==> [6/8] Nginx + HTTPS"
sudo cp docs/deploy/nginx-tandix.conf /etc/nginx/sites-available/tandix.app
sudo ln -sf /etc/nginx/sites-available/tandix.app /etc/nginx/sites-enabled/tandix.app
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" \
  --non-interactive --agree-tos -m "${EMAIL}" --redirect

# --- 7. Colas (Horizon) ---
echo "==> [7/8] Horizon (Supervisor)"
sudo cp docs/deploy/gasai-horizon.conf /etc/supervisor/conf.d/gasai-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart gasai-horizon 2>/dev/null || sudo supervisorctl start gasai-horizon

# --- 8. Scheduler ---
echo "==> [8/8] Cron del scheduler"
( sudo crontab -u www-data -l 2>/dev/null | grep -v 'artisan schedule:run' ; \
  echo "* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1" ) \
  | sudo crontab -u www-data -

echo
echo "==================================================================="
echo " ¡Listo!  https://${DOMAIN}/admin"
echo " Horizon: https://${DOMAIN}/horizon  (solo ${EMAIL})"
echo " Webhook WhatsApp: https://${DOMAIN}/webhooks/whatsapp"
echo " Verify token: ${WA_TOKEN}"
echo " (La contraseña de la BD se guardó en ${APP_DIR}/.env)"
echo "==================================================================="
