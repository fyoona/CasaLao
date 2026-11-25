FROM node:20 AS assets
WORKDIR /app
COPY lao-main/package*.json ./
RUN npm install
COPY lao-main/ .
RUN npm run build

FROM php:8.2-cli
WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git curl libpq-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY lao-main/ .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader


EXPOSE 8080
CMD php artisan migrate --force && php artisan db:seed --class=RoomSeeder --force && php artisan db:seed --class=AdminSeeder --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}