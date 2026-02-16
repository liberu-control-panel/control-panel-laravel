FROM php:8.4-fpm

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
    libicu-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure intl && \
    docker-php-ext-configure zip && \
    docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip sockets

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create application user (non-root)
RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -g appuser -m -s /bin/bash appuser

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY --chown=appuser:appuser composer.json composer.lock ./

# Install application dependencies with cache mount
# GitHub token can be provided via --secret id=github_token for better security
RUN --mount=type=cache,target=/tmp/cache \
    --mount=type=secret,id=github_token,required=false \
    if [ -f /run/secrets/github_token ]; then \
        export COMPOSER_AUTH="{\"github-oauth\": {\"github.com\": \"$(cat /run/secrets/github_token)\"}}"; \
    fi && \
    COMPOSER_CACHE_DIR=/tmp/cache composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist

# Copy existing application directory contents
COPY --chown=appuser:appuser . /var/www/html

# Run post-install scripts
RUN composer run-script post-install-cmd --no-interaction || true

# Create directories for secrets (Docker Swarm/K8s secrets mount point)
RUN mkdir -p /run/secrets && \
    chown -R appuser:appuser /run/secrets

# Set proper permissions for Laravel directories
RUN chown -R appuser:appuser /var/www/html && \
    chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy PHP-FPM configuration for non-root user
COPY .docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

# Copy entrypoint script
COPY --chown=appuser:appuser .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Switch to non-root user
USER appuser

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Use entrypoint to handle secrets and configuration
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
