# LawFirm ERP — Backend (Laravel 11) image
FROM php:8.3-fpm-alpine

# System deps + PHP extensions (PostgreSQL + Redis + common Laravel needs).
# nginx + gettext (envsubst): للتشغيل الإنتاجي (Render) عبر start.sh — لا يؤثّر على
# استخدام docker-compose الذي يبقى على CMD php-fpm أدناه مع nginx منفصل.
RUN apk add --no-cache \
        bash git curl libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
        postgresql-dev postgresql-client icu-dev oniguruma-dev \
        nginx gettext \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip intl pcntl gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first (better layer caching)
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

# App source
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/render/start.sh

# الافتراضي (docker-compose/التطوير): php-fpm فقط خلف nginx منفصل.
# الإنتاج (Render): يتجاوز الأمر إلى docker/render/start.sh (nginx+fpm على $PORT).
EXPOSE 9000
CMD ["php-fpm"]
