#!/bin/sh
set -e
mkdir -p /var/www/html/storage/security /var/www/html/storage/academy /var/www/html/storage/uploads
if [ -f /var/www/html/uploads/.htaccess ]; then
  cp -n /var/www/html/uploads/.htaccess /var/www/html/storage/uploads/.htaccess || true
fi
if [ ! -L /var/www/html/uploads ]; then
  rm -rf /var/www/html/uploads
  ln -sfn /var/www/html/storage/uploads /var/www/html/uploads
fi
chown -R www-data:www-data /var/www/html/storage || true
php /var/www/html/docker/php/cli-install.php
exec docker-php-entrypoint apache2-foreground
