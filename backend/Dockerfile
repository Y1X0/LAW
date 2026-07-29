# LawFirm ERP — Backend (Laravel 11) image
FROM php:8.3-fpm-alpine

# System deps + PHP extensions (PostgreSQL + Redis + common Laravel needs)
RUN apk add --no-cache \
        bash git curl libpng-dev libzip-dev postgresql-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip intl pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first (better layer caching)
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

# App source
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
