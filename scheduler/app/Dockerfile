# SOCIALISTIC application image (app, queue worker, scheduler all share this).
FROM php:8.4-cli-bookworm

# System deps + PHP extensions needed by Laravel + the publishing engine.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip bcmath exif pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 22 for building front-end assets.
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Install PHP deps first (better layer caching).
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-dev --prefer-dist || true

# App source + front-end build.
COPY . .
RUN composer install --no-interaction --no-dev --prefer-dist \
    && npm ci && npm run build \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

# Default: serve the app. The worker/scheduler services override this command.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
