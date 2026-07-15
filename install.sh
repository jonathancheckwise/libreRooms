#!/bin/bash
set -e

echo "Installing LibreRooms"

git config core.autocrlf false
git config core.filemode false
composer install --no-dev --optimize-autoloader
if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
fi
if [ -f .env ]; then
  echo ".env already exists - installation cancelled"
  exit 1
fi
cp .env.example .env
php artisan key:generate
php artisan webcron:token
php artisan optimize
php artisan storage:link

if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
  sudo chown -R :www-data .
  sudo chmod -R 755 .
  sudo chown -R www-data:www-data storage bootstrap/cache .env
  sudo chmod -R 775 storage bootstrap/cache .env
fi

echo "Installation successfull"
echo "Next steps:"
echo "1. Create a database with a user"
echo "2. Start a webserver pointing at public/"
echo "3. Access and configure LibreRooms from your web browser"
echo "4. Add the scheduler in cron (user www-data) - for ex:"
echo '   sudo crontab -u www-data -e'
echo '   * * * * * cd /var/www/html/libreRooms && php artisan schedule:run >> /dev/null 2>&1'
