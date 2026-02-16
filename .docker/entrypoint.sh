#!/usr/bin/env bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_debug() {
    if [ "$APP_DEBUG" = "true" ]; then
        echo -e "${BLUE}[DEBUG]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
    fi
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
        
        max_attempts=${DB_WAIT_TIMEOUT:-60}
        attempt=1
        
        while [ $attempt -le $max_attempts ]; do
            if nc -z "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; then
                log_info "Database connection successful!"
                
                # Additional check: try to connect with MySQL client if available
                if command -v mysql &> /dev/null && [ -n "$DB_USERNAME" ] && [ -n "$DB_PASSWORD" ]; then
                    log_debug "Verifying database credentials..."
                    if mysql -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
                        log_info "Database authentication successful!"
                    else
                        log_warn "Database port is open but authentication failed"
                    fi
                fi
                
                return 0
            fi
            
            log_debug "Database not ready, attempt $attempt/$max_attempts..."
            sleep 2
            attempt=$((attempt + 1))
        done
        
        log_error "Database did not become ready after $max_attempts attempts"
        if [ "$FAIL_ON_DB_ERROR" = "true" ]; then
            return 1
        else
            log_warn "Continuing despite database connection failure (FAIL_ON_DB_ERROR not set)"
            return 0
        fi
    fi
}

# Wait for Redis to be ready
wait_for_redis() {
    if [ -n "$REDIS_HOST" ]; then
        log_info "Waiting for Redis at $REDIS_HOST:${REDIS_PORT:-6379}..."
        
        max_attempts=30
        attempt=1
        
        while [ $attempt -le $max_attempts ]; do
            if nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}" 2>/dev/null; then
                log_info "Redis is ready!"
                return 0
            fi
            
            log_debug "Redis not ready, attempt $attempt/$max_attempts..."
            sleep 1
            attempt=$((attempt + 1))
        done
        
        log_warn "Redis did not become ready, continuing anyway..."
        return 0
    fi
}

# Initialize Laravel application
initialize_laravel() {
    log_info "Initializing Laravel application..."
    
    # Verify we're in the correct directory
    if [ ! -f "artisan" ]; then
        log_error "artisan file not found. Are we in the correct directory?"
        pwd
        return 1
    fi
    
    # Create storage directories if they don't exist
    log_debug "Creating storage directories..."
    mkdir -p /var/www/html/storage/framework/cache
    mkdir -p /var/www/html/storage/framework/sessions
    mkdir -p /var/www/html/storage/framework/views
    mkdir -p /var/www/html/storage/logs
    mkdir -p /var/www/html/bootstrap/cache
    
    # Set proper permissions (only if running as root or with correct permissions)
    if [ "$(id -u)" = "0" ]; then
        log_debug "Setting directory permissions..."
        chown -R appuser:appuser /var/www/html/storage /var/www/html/bootstrap/cache || true
        chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true
    fi
    
    # Check for .env file
    if [ ! -f ".env" ]; then
        log_warn ".env file not found!"
        if [ -f ".env.example" ]; then
            log_info "Copying .env.example to .env..."
            cp .env.example .env
        else
            log_error "Neither .env nor .env.example found!"
        fi
    fi
    
    # Only run migrations if explicitly enabled
    if [ "$RUN_MIGRATIONS" = "true" ]; then
        log_info "Running database migrations..."
        if php artisan migrate --force 2>&1 | tee /tmp/migration.log; then
            log_info "Migrations completed successfully"
        else
            log_error "Migrations failed. Check /tmp/migration.log for details"
            cat /tmp/migration.log
            if [ "$FAIL_ON_MIGRATION_ERROR" = "true" ]; then
                return 1
            fi
        fi
    fi
    
    # Run seeders if explicitly enabled
    if [ "$RUN_SEEDERS" = "true" ]; then
        log_info "Running database seeders..."
        php artisan db:seed --force || log_warn "Seeders failed or already run"
    fi
    
    # Cache configuration for better performance
    if [ "$APP_ENV" = "production" ]; then
        log_info "Optimizing for production environment..."
        php artisan config:cache || log_warn "Config cache failed"
        php artisan route:cache || log_warn "Route cache failed"
        php artisan view:cache || log_warn "View cache failed"
        php artisan optimize || log_warn "Optimization failed"
    else
        log_info "Clearing cache for ${APP_ENV:-development} environment..."
        php artisan config:clear || true
        php artisan route:clear || true
        php artisan view:clear || true
        php artisan cache:clear || true
    fi
    
    log_info "Laravel initialization complete"
}

# Signal handler for graceful shutdown
graceful_shutdown() {
    log_info "Received shutdown signal, performing graceful shutdown..."
    
    # If running PHP-FPM, send quit signal
    if [ -f "/var/run/php-fpm.pid" ]; then
        kill -QUIT $(cat /var/run/php-fpm.pid) 2>/dev/null || true
    fi
    
    exit 0
}

# Set up signal handlers
trap graceful_shutdown SIGTERM SIGINT SIGQUIT

# Main execution
main() {
    log_info "=== Container Initialization Started ==="
    log_info "Environment: ${APP_ENV:-production}"
    log_info "PHP Version: $(php -v | head -n 1)"
    
    # Load secrets first
    load_secrets
    
    # Wait for dependencies if configured
    if [ "$WAIT_FOR_DB" != "false" ]; then
        wait_for_database || {
            log_error "Database wait failed"
            if [ "$FAIL_ON_DB_ERROR" = "true" ]; then
                exit 1
            fi
        }
    fi
    
    if [ "$WAIT_FOR_REDIS" = "true" ]; then
        wait_for_redis
    fi
    
    # Initialize Laravel if not disabled
    if [ "$SKIP_INIT" != "true" ]; then
        initialize_laravel || {
            log_error "Laravel initialization failed"
            if [ "$FAIL_ON_INIT_ERROR" = "true" ]; then
                exit 1
            fi
        }
    fi
    
    log_info "=== Container Initialization Complete ==="
    
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
