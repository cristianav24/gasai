#!/usr/bin/env bash
# Redeploy de GasAI en el VPS. Ejecutar desde /var/www/gasai tras un git pull.
# Uso: cd /var/www/gasai && ./docs/deploy/deploy.sh
set -euo pipefail

echo "==> Modo mantenimiento"
php artisan down || true

echo "==> Actualizando código"
git pull --ff-only

echo "==> Dependencias PHP (producción)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Assets del frontend"
npm ci
npm run build

echo "==> Migraciones"
php artisan migrate --force

echo "==> Cachés (config/route/view/events) + Filament"
php artisan optimize
php artisan filament:optimize

echo "==> Reiniciando colas (Horizon)"
php artisan horizon:terminate   # supervisor lo levanta de nuevo

echo "==> Saliendo de mantenimiento"
php artisan up

echo "==> Listo."
