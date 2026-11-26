FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY ["aplikasi kasir awwalkitchen/", "/var/www/html/"]

RUN a2enmod rewrite \
    && chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

CMD ["apache2-foreground"]
