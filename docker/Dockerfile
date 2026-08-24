FROM php:8.5-fpm


RUN apt-get update && apt-get install -y \
    $PHPIZE_DEPS \
    libpq-dev \
    libzip-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd exif \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www
