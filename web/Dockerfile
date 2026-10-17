FROM php:8.2-apache

# PDO SQLite + rewrite for the API routes
RUN apt-get update \
 && apt-get install -y --no-install-recommends libsqlite3-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo pdo_sqlite \
 && a2enmod rewrite

COPY src/ /var/www/html/
COPY lab.conf /etc/apache2/conf-enabled/lab.conf
COPY lab.ini  /usr/local/etc/php/conf.d/lab.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p /var/www/data /var/www/private /var/www/html/uploads \
 && chown -R www-data:www-data /var/www

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
