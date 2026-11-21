FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo_mysql

COPY . /var/www/html/

COPY config.docker.php /var/www/html/config.php

RUN a2enmod rewrite

EXPOSE 80