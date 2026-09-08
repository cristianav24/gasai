# Deploy de GasAI en tandix.app (VPS Ubuntu / Contabo)

Guía para publicar el sistema en `tandix.app`. Los comandos se corren **en el VPS**
por SSH. Reemplazan lo que hoy sirva ese dominio: **haz un respaldo antes** (ver §0).

> El panel queda en `https://tandix.app/admin`. Cada negocio (tenant) entra por
> `https://tandix.app/admin/su-slug` (tenancy por ruta). El subdominio por tenant
> (`slug.tandix.app`) es una mejora futura, no requerida para funcionar.

## 0. Respaldo de lo que ya existe

```bash
# Config de nginx actual y (si aplica) base de datos previa
sudo cp -r /etc/nginx/sites-available ~/backup-nginx-$(date +%F)
# Si tandix.app ya tenía una app, respáldala antes de reemplazarla.
```

## 1. Paquetes del sistema (una sola vez)

```bash
sudo apt update
sudo apt install -y nginx postgresql redis-server supervisor unzip git \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd \
  php8.3-pcntl php8.3-posix
# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
# Node 20 (para compilar assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

> `php8.3-redis` (phpredis) es necesario para Horizon en el servidor.

## 2. Base de datos PostgreSQL

```bash
sudo -u postgres psql <<'SQL'
CREATE DATABASE gasai;
CREATE USER gasai WITH ENCRYPTED PASSWORD 'CONTRASEÑA_FUERTE_DB';
GRANT ALL PRIVILEGES ON DATABASE gasai TO gasai;
\c gasai
GRANT ALL ON SCHEMA public TO gasai;
SQL
```

## 3. Código y dependencias

```bash
sudo mkdir -p /var/www/gasai && sudo chown -R $USER:$USER /var/www/gasai
git clone <URL_DE_TU_REPO> /var/www/gasai   # o sube el código por scp/rsync
cd /var/www/gasai

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp docs/deploy/.env.production.example .env
nano .env                 # completa DB_PASSWORD, DEEPSEEK_API_KEY, WHATSAPP_VERIFY_TOKEN
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # opcional: crea el tenant/base de ejemplo
php artisan storage:link
php artisan optimize
php artisan filament:optimize
```

Permisos para el servidor web:

```bash
sudo chown -R www-data:www-data /var/www/gasai/storage /var/www/gasai/bootstrap/cache
```

## 4. Nginx + HTTPS

```bash
sudo cp docs/deploy/nginx-tandix.conf /etc/nginx/sites-available/tandix.app
sudo ln -sf /etc/nginx/sites-available/tandix.app /etc/nginx/sites-enabled/tandix.app
sudo rm -f /etc/nginx/sites-enabled/default   # si el default choca con el dominio
sudo nginx -t && sudo systemctl reload nginx

# Certificado TLS (asegúrate que el DNS de tandix.app apunta a este VPS)
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tandix.app -d www.tandix.app
```

## 5. Colas (Horizon) con Supervisor

```bash
sudo cp docs/deploy/gasai-horizon.conf /etc/supervisor/conf.d/gasai-horizon.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start gasai-horizon
```

Panel de Horizon: `https://tandix.app/horizon` (solo lo ve `francia24vs@gmail.com`).

## 6. Programador de tareas (scheduler)

```bash
sudo crontab -u www-data -e
# añade esta línea:
* * * * * cd /var/www/gasai && php artisan schedule:run >> /dev/null 2>&1
```

## 7. WhatsApp

En el panel de Meta, configura el webhook con:
- **Callback URL:** `https://tandix.app/webhooks/whatsapp`
- **Verify token:** el que pusiste en `WHATSAPP_VERIFY_TOKEN`

Luego, en el panel (`/admin/su-slug` → WhatsApp), pega `phone_number_id`, `waba_id`
y el access token, y prueba la conexión.

## Redeploys posteriores

```bash
cd /var/www/gasai && ./docs/deploy/deploy.sh
```

## Notas

- **HTTPS obligatorio**: el `.env` marca `SESSION_SECURE_COOKIE=true`; sin TLS no
  habrá sesión. Corre certbot antes de usar el panel.
- **App móvil del repartidor**: en `mobile/app.json` pon `apiBaseUrl` = `https://tandix.app`.
