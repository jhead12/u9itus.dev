# Dial4Dough Production Dockerfile for Railway Metal Build
# Uses PHP CLI since we run php artisan serve (not FPM)

FROM php:8.2-cli-alpine

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
    bash

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm ci && npm run build

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
