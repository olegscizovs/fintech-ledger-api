FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_pgsql bcmath redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
