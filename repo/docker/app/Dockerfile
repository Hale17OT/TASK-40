FROM php:8.4-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    linux-headers \
    supervisor \
    bash \
    git \
    curl \
    nodejs \
    npm \
    postgresql-client

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY src/composer.json src/composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction 2>/dev/null || true

# Copy package files for Node
COPY src/package.json src/package-lock.json* ./

# Copy full application
COPY src/ .

# Install dependencies (dummy secrets for artisan package:discover at build time only)
RUN PAYMENT_HMAC_KEY=build-placeholder DEVICE_FINGERPRINT_SALT=build-placeholder \
    composer install --optimize-autoloader --no-interaction
RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Supervisor config
COPY docker/app/supervisord.conf /etc/supervisord.conf
COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Log directory
RUN mkdir -p /var/log/harborbite && chown www-data:www-data /var/log/harborbite

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
