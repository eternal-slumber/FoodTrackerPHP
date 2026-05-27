FROM php:8.5-fpm


RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd exif

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www
