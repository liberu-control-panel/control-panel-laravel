#!/usr/bin/env bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to read secrets from files
load_secrets() {
    log_info "Loading secrets from files..."
    
    # Check if we're running in Docker/Kubernetes with secrets
    if [ -d "/run/secrets" ]; then
        log_info "Secrets directory found at /run/secrets"
        
        # Database password
        if [ -f "/run/secrets/db_password" ]; then
            export DB_PASSWORD=$(cat /run/secrets/db_password)
            log_info "Loaded DB_PASSWORD from secret file"
        fi
        
        # Database root password
        if [ -f "/run/secrets/db_root_password" ]; then
            export DB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
            log_info "Loaded DB_ROOT_PASSWORD from secret file"
        fi
        
        # APP_KEY
        if [ -f "/run/secrets/app_key" ]; then
            export APP_KEY=$(cat /run/secrets/app_key)
            log_info "Loaded APP_KEY from secret file"
        fi
        
        # Redis password
        if [ -f "/run/secrets/redis_password" ]; then
            export REDIS_PASSWORD=$(cat /run/secrets/redis_password)
            log_info "Loaded REDIS_PASSWORD from secret file"
        fi
        
        # AWS/S3 credentials
        if [ -f "/run/secrets/aws_access_key_id" ]; then
            export AWS_ACCESS_KEY_ID=$(cat /run/secrets/aws_access_key_id)
            log_info "Loaded AWS_ACCESS_KEY_ID from secret file"
        fi
        
        if [ -f "/run/secrets/aws_secret_access_key" ]; then
            export AWS_SECRET_ACCESS_KEY=$(cat /run/secrets/aws_secret_access_key)
            log_info "Loaded AWS_SECRET_ACCESS_KEY from secret file"
        fi
    else
        log_warn "No secrets directory found at /run/secrets, using environment variables"
    fi
    
    # Support for _FILE suffix environment variables (Docker Swarm style)
    for var in $(env | grep '_FILE=' | sed 's/=.*//'); do
        file_path=$(eval echo \$$var)
        if [ -f "$file_path" ]; then
            var_name=${var%_FILE}
            export $var_name=$(cat $file_path)
            log_info "Loaded $var_name from file: $file_path"
        fi
    done
}

# Wait for database to be ready
wait_for_database() {
    if [ -n "$DB_HOST" ]; then
        log_info "Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
        
        max_attempts=30
        attempt=1
        
        while [ $attempt -le $max_attempts ]; do
            if nc -z "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; then
                log_info "Database is ready!"
                return 0
            fi
            
            log_warn "Database not ready, attempt $attempt/$max_attempts..."
            sleep 2
            attempt=$((attempt + 1))
        done
        
        log_error "Database did not become ready in time"
        return 1
    fi
}

# Initialize Laravel application
initialize_laravel() {
    log_info "Initializing Laravel application..."
    
    # Create storage directories if they don't exist
    mkdir -p /var/www/html/storage/framework/cache
    mkdir -p /var/www/html/storage/framework/sessions
    mkdir -p /var/www/html/storage/framework/views
    mkdir -p /var/www/html/storage/logs
    mkdir -p /var/www/html/bootstrap/cache
    
    # Only run migrations if explicitly enabled
    if [ "$RUN_MIGRATIONS" = "true" ]; then
        log_info "Running database migrations..."
        php artisan migrate --force || log_warn "Migrations failed or already run"
    fi
    
    # Cache configuration for better performance
    if [ "$APP_ENV" = "production" ]; then
        log_info "Caching configuration for production..."
        php artisan config:cache || log_warn "Config cache failed"
        php artisan route:cache || log_warn "Route cache failed"
        php artisan view:cache || log_warn "View cache failed"
    else
        log_info "Clearing cache for non-production environment..."
        php artisan config:clear || true
        php artisan route:clear || true
        php artisan view:clear || true
    fi
}

# Main execution
main() {
    log_info "Starting container initialization..."
    
    # Load secrets first
    load_secrets
    
    # Wait for database if configured
    if [ "$WAIT_FOR_DB" != "false" ]; then
        wait_for_database || log_warn "Skipping database wait"
    fi
    
    # Initialize Laravel if not disabled
    if [ "$SKIP_INIT" != "true" ]; then
        initialize_laravel
    fi
    
    log_info "Container initialization complete!"
    
    # Execute the main command
    if [ $# -gt 0 ]; then
        log_info "Executing command: $@"
        exec "$@"
    else
        log_info "Starting PHP-FPM..."
        exec php-fpm
    fi
}

# Run main function
main "$@"
