#!/bin/bash
set -e

echo "Installing LibreRooms"

git config core.autocrlf false
git config core.filemode false
composer install --no-dev --optimize-autoloader
npm ci
npm run build
if [ -f .env ]; then
  echo ".env already exists - installation cancelled"
  exit 1
fi
cp .env.example .env
php artisan key:generate
php artisan webcron:token
php artisan optimize
php artisan storage:link
sudo chown -R :www-data .
sudo chmod -R 755 .
sudo chown -R www-data:www-data storage bootstrap/cache .env
sudo chmod -R 775 storage bootstrap/cache .env

echo "Installation successfull"
echo "Next steps:"
echo "1. Create a database with a user"
echo "2. Start a webserver pointing at public/"
echo "3. Access and configure LibreRooms from your web browser"
echo "4. Add the scheduler in cron (user www-data) - for ex:"
echo '   sudo crontab -u www-data -e'
echo '   * * * * * cd /var/www/html/libreRooms && php artisan schedule:run >> /dev/null 2>&1'
