FROM php:8.3-apache

RUN a2enmod rewrite

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY src/ /var/www/html/

WORKDIR /var/www/html
