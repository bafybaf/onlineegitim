FROM php:8.3-apache-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends libzip-dev libpng-dev libicu-dev libjpeg62-turbo-dev libfreetype6-dev ffmpeg \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) pdo_mysql mysqli zip gd intl opcache \
  && a2enmod rewrite headers remoteip proxy proxy_http \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/oi-entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html

RUN sed -i 's/\r$//' /usr/local/bin/oi-entrypoint.sh \
  && chmod +x /usr/local/bin/oi-entrypoint.sh \
  && sed -i 's|RewriteBase /online-ilahiyat/|RewriteBase /|' /var/www/html/.htaccess \
  && mkdir -p /var/www/html/storage/security /var/www/html/storage/academy \
  && chown -R www-data:www-data /var/www/html \
  && find /var/www/html/storage -type d -exec chmod 775 {} \;

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/oi-entrypoint.sh"]
