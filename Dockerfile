# force rebuild
# Stage 1: Build
FROM composer:2 AS build
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Runtime
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    zip unzip nginx \
    libpng-dev libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=build --chown=www-data:www-data /app .

COPY ./docker/nginx.conf /etc/nginx/nginx.conf

RUN touch .env

RUN php -v
RUN php -m

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY ./docker/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
