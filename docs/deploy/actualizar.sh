#!/usr/bin/env bash
# Actualiza SolarFix en el servidor con la última versión de GitHub.
# Uso (desde la carpeta de la app):  bash docs/deploy/actualizar.sh
# Hace un respaldo antes, pone la app en mantenimiento mientras actualiza y la vuelve a abrir.
set -euo pipefail
cd "$(dirname "$0")/../.."

echo "→ Respaldo previo"
php artisan solarfix:backup

echo "→ Modo mantenimiento"
php artisan down --retry=30 || true
trap 'php artisan up' EXIT

echo "→ Código"
git pull --ff-only

echo "→ Dependencias"
composer install --no-dev --optimize-autoloader --no-interaction
if command -v npm >/dev/null; then
    npm ci && npm run build
else
    echo "  (sin npm en el servidor: sube public/build compilado en tu equipo)"
fi

echo "→ Base de datos"
php artisan migrate --force
php artisan db:seed --class=ChecklistSeeder --force

echo "→ Cachés"
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

echo "→ Revisión"
php artisan solarfix:check || true
echo "Listo."
