#!/bin/bash
################################################################################
# Liberu Control Panel - Automated Backup Script
# 
# This script creates backups of:
# - Database (MySQL/PostgreSQL)
# - Application files
# - Configuration files
# - Storage directory
################################################################################

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/control-panel}"
APP_DIR="${APP_DIR:-/var/www/control-panel}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="control-panel-backup-${DATE}"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

# Check if running as root or with proper permissions
check_permissions() {
    if [ ! -w "$BACKUP_DIR" ] && [ ! -w "$(dirname "$BACKUP_DIR")" ]; then
        log_error "No write permission for backup directory: $BACKUP_DIR"
        exit 1
    fi
}

# Create backup directory
create_backup_dir() {
    log_info "Creating backup directory: $BACKUP_DIR/$BACKUP_NAME"
    mkdir -p "$BACKUP_DIR/$BACKUP_NAME"
}

# Load environment variables
load_env() {
    if [ -f "$APP_DIR/.env" ]; then
        log_info "Loading environment variables..."
        set -a
        source "$APP_DIR/.env"
        set +a
    else
        log_warning ".env file not found at $APP_DIR/.env"
    fi
}

# Backup database
backup_database() {
    log_info "Backing up database..."
    
    local db_connection="${DB_CONNECTION:-mysql}"
    local backup_file="$BACKUP_DIR/$BACKUP_NAME/database.sql.gz"
    
    case $db_connection in
        mysql)
            log_info "Backing up MySQL database: ${DB_DATABASE}"
            if mysqldump \
                --host="${DB_HOST:-localhost}" \
                --port="${DB_PORT:-3306}" \
                --user="${DB_USERNAME}" \
                --password="${DB_PASSWORD}" \
                --single-transaction \
                --quick \
                --lock-tables=false \
                "${DB_DATABASE}" | gzip > "$backup_file"; then
                log_success "MySQL database backed up successfully"
            else
                log_error "MySQL database backup failed"
                return 1
            fi
            ;;
        pgsql)
            log_info "Backing up PostgreSQL database: ${DB_DATABASE}"
            if PGPASSWORD="${DB_PASSWORD}" pg_dump \
                --host="${DB_HOST:-localhost}" \
                --port="${DB_PORT:-5432}" \
                --username="${DB_USERNAME}" \
                --format=plain \
                "${DB_DATABASE}" | gzip > "$backup_file"; then
                log_success "PostgreSQL database backed up successfully"
            else
                log_error "PostgreSQL database backup failed"
                return 1
            fi
            ;;
        *)
            log_warning "Unknown database connection: $db_connection"
            return 1
            ;;
    esac
    
    # Get database size
    local db_size=$(du -h "$backup_file" | cut -f1)
    log_info "Database backup size: $db_size"
}

# Backup application files
backup_application() {
    log_info "Backing up application files..."
    
    local app_backup="$BACKUP_DIR/$BACKUP_NAME/application.tar.gz"
    
    # Create exclude file
    local exclude_file=$(mktemp)
    cat > "$exclude_file" << 'EOF'
vendor/
node_modules/
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*
.git/
.env
EOF
    
    if tar -czf "$app_backup" \
        -C "$(dirname "$APP_DIR")" \
        --exclude-from="$exclude_file" \
        "$(basename "$APP_DIR")"; then
        log_success "Application files backed up successfully"
    else
        log_error "Application backup failed"
        rm -f "$exclude_file"
        return 1
    fi
    
    rm -f "$exclude_file"
    
    # Get application backup size
    local app_size=$(du -h "$app_backup" | cut -f1)
    log_info "Application backup size: $app_size"
}

# Backup storage directory
backup_storage() {
    log_info "Backing up storage directory..."
    
    if [ -d "$APP_DIR/storage" ]; then
        local storage_backup="$BACKUP_DIR/$BACKUP_NAME/storage.tar.gz"
        
        if tar -czf "$storage_backup" \
            -C "$APP_DIR" \
            --exclude='storage/logs/*' \
            --exclude='storage/framework/cache/*' \
            --exclude='storage/framework/sessions/*' \
            --exclude='storage/framework/views/*' \
            storage; then
            log_success "Storage directory backed up successfully"
            
            local storage_size=$(du -h "$storage_backup" | cut -f1)
            log_info "Storage backup size: $storage_size"
        else
            log_error "Storage backup failed"
            return 1
        fi
    else
        log_warning "Storage directory not found"
    fi
}

# Backup configuration files
backup_configs() {
    log_info "Backing up configuration files..."
    
    local config_backup="$BACKUP_DIR/$BACKUP_NAME/configs.tar.gz"
    
    # Files to backup
    local config_files=(
        "$APP_DIR/.env"
        "/etc/nginx/sites-available/control-panel"
        "/etc/systemd/system/control-panel-*.service"
    )
    
    # Create list of existing files
    local existing_files=()
    for file in "${config_files[@]}"; do
        if [ -f "$file" ] || [ -L "$file" ]; then
            existing_files+=("$file")
        fi
    done
    
    if [ ${#existing_files[@]} -gt 0 ]; then
        if tar -czf "$config_backup" "${existing_files[@]}" 2>/dev/null; then
            log_success "Configuration files backed up successfully"
        else
            log_warning "Some configuration files could not be backed up"
        fi
    else
        log_warning "No configuration files found to backup"
    fi
}

# Create backup manifest
create_manifest() {
    log_info "Creating backup manifest..."
    
    local manifest="$BACKUP_DIR/$BACKUP_NAME/manifest.txt"
    
    cat > "$manifest" << EOF
Liberu Control Panel Backup
============================
Backup Date: $(date +'%Y-%m-%d %H:%M:%S')
Hostname: $(hostname)
Backup Name: $BACKUP_NAME
App Directory: $APP_DIR
PHP Version: $(php -v | head -n 1)
Laravel Version: $(cd "$APP_DIR" && php artisan --version 2>/dev/null || echo "Unknown")

Database:
  Connection: ${DB_CONNECTION:-unknown}
  Database: ${DB_DATABASE:-unknown}
  Host: ${DB_HOST:-unknown}

Backup Contents:
$(ls -lh "$BACKUP_DIR/$BACKUP_NAME" | tail -n +2)

Total Backup Size: $(du -sh "$BACKUP_DIR/$BACKUP_NAME" | cut -f1)
EOF
    
    log_success "Manifest created"
}

# Compress entire backup
compress_backup() {
    log_info "Compressing backup..."
    
    local compressed_backup="$BACKUP_DIR/${BACKUP_NAME}.tar.gz"
    
    if tar -czf "$compressed_backup" -C "$BACKUP_DIR" "$BACKUP_NAME"; then
        log_success "Backup compressed successfully"
        
        # Remove uncompressed backup
        rm -rf "$BACKUP_DIR/$BACKUP_NAME"
        
        local final_size=$(du -h "$compressed_backup" | cut -f1)
        log_info "Final backup size: $final_size"
        log_success "Backup saved to: $compressed_backup"
    else
        log_error "Backup compression failed"
        return 1
    fi
}

# Clean old backups
clean_old_backups() {
    log_info "Cleaning backups older than $RETENTION_DAYS days..."
    
    local deleted_count=0
    
    while IFS= read -r -d '' backup; do
        log_info "Deleting old backup: $(basename "$backup")"
        rm -f "$backup"
        deleted_count=$((deleted_count + 1))
    done < <(find "$BACKUP_DIR" -name "control-panel-backup-*.tar.gz" -type f -mtime +$RETENTION_DAYS -print0)
    
    if [ $deleted_count -gt 0 ]; then
        log_success "Deleted $deleted_count old backup(s)"
    else
        log_info "No old backups to delete"
    fi
}

# Upload to S3 (optional)
upload_to_s3() {
    if [ -n "${AWS_S3_BACKUP_BUCKET:-}" ]; then
        log_info "Uploading backup to S3: $AWS_S3_BACKUP_BUCKET"
        
        local compressed_backup="$BACKUP_DIR/${BACKUP_NAME}.tar.gz"
        
        if command -v aws &> /dev/null; then
            if aws s3 cp "$compressed_backup" "s3://$AWS_S3_BACKUP_BUCKET/backups/" --storage-class STANDARD_IA; then
                log_success "Backup uploaded to S3 successfully"
            else
                log_error "Failed to upload backup to S3"
                return 1
            fi
        else
            log_warning "AWS CLI not installed, skipping S3 upload"
        fi
    fi
}

# Send notification
send_notification() {
    local status="$1"
    local message="$2"
    
    # Send email notification if configured
    if [ -n "${BACKUP_NOTIFICATION_EMAIL:-}" ]; then
        echo "$message" | mail -s "Control Panel Backup - $status" "$BACKUP_NOTIFICATION_EMAIL" 2>/dev/null || true
    fi
    
    # Webhook notification if configured
    if [ -n "${BACKUP_WEBHOOK_URL:-}" ]; then
        curl -X POST "$BACKUP_WEBHOOK_URL" \
            -H "Content-Type: application/json" \
            -d "{\"status\":\"$status\",\"message\":\"$message\",\"timestamp\":\"$(date -Iseconds)\"}" \
            2>/dev/null || true
    fi
}

# Main backup function
main() {
    log_info "╔═══════════════════════════════════════════════════════╗"
    log_info "║  Liberu Control Panel - Backup Script               ║"
    log_info "╚═══════════════════════════════════════════════════════╝"
    echo ""
    
    # Check permissions
    check_permissions
    
    # Create backup directory
    create_backup_dir
    
    # Load environment
    load_env
    
    local failed=0
    
    # Run backup tasks
    backup_database || failed=1
    backup_application || failed=1
    backup_storage || failed=1
    backup_configs || true  # Don't fail on config backup
    
    # Create manifest
    create_manifest
    
    # Compress backup
    if [ $failed -eq 0 ]; then
        compress_backup || failed=1
        
        # Upload to S3 if configured
        upload_to_s3 || true
        
        # Clean old backups
        clean_old_backups || true
        
        echo ""
        log_success "╔═══════════════════════════════════════════════════════╗"
        log_success "║  Backup Completed Successfully                       ║"
        log_success "╚═══════════════════════════════════════════════════════╝"
        
        send_notification "SUCCESS" "Control panel backup completed successfully"
        
        exit 0
    else
        echo ""
        log_error "╔═══════════════════════════════════════════════════════╗"
        log_error "║  Backup Failed                                        ║"
        log_error "╚═══════════════════════════════════════════════════════╝"
        
        send_notification "FAILED" "Control panel backup failed"
        
        exit 1
    fi
}

# Run main function
main "$@"
