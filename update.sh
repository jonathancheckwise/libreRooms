#!/bin/bash
set -e

echo "Updating LibreRooms"

git pull origin main

composer install --no-dev --optimize-autoloader

if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
fi

php artisan migrate --force

php artisan optimize:clear
php artisan optimize

if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
  sudo chown -R :www-data .
  sudo chmod -R 755 .
  sudo chown -R www-data:www-data storage bootstrap/cache .env
  sudo chmod -R 775 storage bootstrap/cache .env
fi

echo "Update successful"
