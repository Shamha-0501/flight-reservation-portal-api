# ---- 1) Composer deps ----
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY . .
RUN rm -f bootstrap/cache/*.php
RUN composer dump-autoload --no-dev --optimize --no-scripts


# ---- 2) PHP-FPM runtime ----
FROM php:8.4-fpm-bookworm
WORKDIR /var/www/html

RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    curl unzip \
    zlib1g-dev libzip-dev; \
  docker-php-ext-install -j"$(nproc)" pdo_mysql zip opcache; \
  pecl channel-update pecl.php.net; \
  printf "\n" | pecl install redis; \
  docker-php-ext-enable redis; \
  apt-get purge -y --auto-remove $PHPIZE_DEPS; \
  rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --from=vendor /app /var/www/html

RUN php artisan package:discover --ansi || true

RUN mkdir -p storage bootstrap/cache \
  && chown -R www-data:www-data /var/www/html \
  && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
