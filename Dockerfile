# Multi-stage build for optimized image size
# Stage 1: Composer dependencies
FROM composer:latest AS composer-dependencies

WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Stage 2: Production image
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    netcat-openbsd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Create application user (non-root)
RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -g appuser -m -s /bin/bash appuser

# Set working directory
WORKDIR /var/www/html

# Copy application from composer stage
COPY --from=composer-dependencies --chown=appuser:appuser /app /var/www/html

# Create directories for secrets (Docker Swarm/K8s secrets mount point)
RUN mkdir -p /run/secrets && \
    chown -R appuser:appuser /run/secrets

# Set proper permissions for Laravel directories
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy PHP-FPM configuration for non-root user
COPY .docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

# Copy PHP configuration
COPY .docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy entrypoint script
COPY --chown=appuser:appuser .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php artisan schedule:run --verbose --no-interaction || exit 1

# Switch to non-root user
USER appuser

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Use entrypoint to handle secrets and configuration
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
