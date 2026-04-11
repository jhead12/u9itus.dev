# U9itus Production Dockerfile for Railway Metal Build
# Uses PHP CLI since we run php artisan serve (not FPM)

FROM php:8.4-cli-alpine

# Install system dependencies, PHP extensions, and upload limits in one layer.
RUN apk add --no-cache \
    git \
    curl \
    curl-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    linux-headers \
    zip \
    unzip \
    nodejs \
    npm \
    mysql-client \
    bash \
    ffmpeg && \
    docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        curl \
        dom \
        intl \
        simplexml \
        sockets \
        xml \
        xmlwriter \
        zip && \
    mkdir -p /usr/local/etc/php/conf.d && \
    cat > /usr/local/etc/php/conf.d/uploads.ini <<'EOF'
upload_max_filesize=1024M
post_max_size=1050M
memory_limit=512M
max_file_uploads=20
EOF

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Cache-bust argument — increment to force fresh composer install layer
ARG CACHE_BUST=2

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Copy application files
COPY . .

# Composer runs Artisan package discovery during install, so provide a build-time env file.
RUN [ -f .env ] || cp .env.example .env && \
    composer install --no-dev --optimize-autoloader --no-interaction && \
    npm install --no-audit && \
    npm run build && \
    mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache && \
    composer dump-autoload --optimize && \
    chmod +x wait-for-db.sh

# Expose port (Railway will override this)
EXPOSE 8080

# Start via wait-for-db.sh which handles migrations and server startup
CMD ["./wait-for-db.sh"]
