# U9itus Production Dockerfile for Railway Metal Build
# Uses PHP CLI since we run php artisan serve (not FPM)

FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    mysql-client \
    bash \
    ffmpeg

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Raise PHP body/upload limits to match 1 GB campaign video upload expectations.
RUN mkdir -p /usr/local/etc/php/conf.d && \
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

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm install --no-audit && npm run build

# Create Laravel directories with correct permissions
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Make wait-for-db script executable
RUN chmod +x wait-for-db.sh

# Expose port (Railway will override this)
EXPOSE 8080

# Start via wait-for-db.sh which handles migrations and server startup
CMD ["./wait-for-db.sh"]
