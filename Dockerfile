# ---------- Tahap 1: build CSS/JS (Vite) ----------
FROM node:20 AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install --legacy-peer-deps --no-audit --no-fund
COPY . .
RUN npm run build

# ---------- Tahap 2: aplikasi Laravel (PHP + Apache) ----------
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
      git unzip libzip-dev libpq-dev libpng-dev \
 && docker-php-ext-install pdo_mysql pdo_pgsql zip gd bcmath \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache diarahkan ke folder public Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# Saat container jalan: pakai port dari Render, migrate, lalu start Apache
CMD sed -i "s/Listen 80/Listen ${PORT:-10000}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf \
 && php artisan config:cache \
 && php artisan migrate --force \
 && apache2-foreground
