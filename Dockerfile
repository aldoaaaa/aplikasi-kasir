FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/

RUN sed -ri -e 's!/var/www/html!/var/www/html/aplikasi kasir awwalkitchen!g' /etc/apache2/sites-available/*.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite \
    && chown -R www-data:www-data /var/www/html/

CMD ["apache2-foreground"]
