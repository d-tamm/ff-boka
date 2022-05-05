FROM php:7.4-apache

#COPY php.ini /usr/local/etc/php/
RUN apt-get update && apt-get install -y \
        zlib1g-dev \
        libfreetype6-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql
RUN a2enmod rewrite
