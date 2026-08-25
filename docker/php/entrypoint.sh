#!/bin/sh
set -e
mkdir -p /var/www/html/storage/security /var/www/html/storage/academy
chown -R www-data:www-data /var/www/html/storage || true
php /var/www/html/docker/php/cli-install.php
exec docker-php-entrypoint apache2-foreground
