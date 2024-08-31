#!/bin/bash

# Database credentials
DB_USER="myapp"
DB_NAME="myapp"
BACKUP_DIR="/var/backups/mysql"

# Ensure backup directory exists
mkdir -p $BACKUP_DIR

# Function to read password from Docker secret
get_db_password() {
    cat /run/secrets/db_password
}

# Backup function
backup_db() {
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql.gz"
    
    mysqldump -u$DB_USER -p$(get_db_password) $DB_NAME | gzip > $BACKUP_FILE
    
    if [ $? -eq 0 ]; then
        echo "Database backup completed successfully: $BACKUP_FILE"
    else
        echo "Error: Database backup failed"
        exit 1
    fi
}

# Restore function
restore_db() {
    if [ -z "$1" ]; then
        echo "Error: Please provide the backup file to restore"
        exit 1
    fi
    
    BACKUP_FILE=$1
    
    if [ ! -f "$BACKUP_FILE" ]; then
        echo "Error: Backup file does not exist"
        exit 1
    fi
    
    gunzip < $BACKUP_FILE | mysql -u$DB_USER -p$(get_db_password) $DB_NAME
    
    if [ $? -eq 0 ]; then
        echo "Database restore completed successfully"
    else
        echo "Error: Database restore failed"
        exit 1
    fi
}

# Main script logic
case "$1" in
    backup)
        backup_db
        ;;
    restore)
        restore_db $2
        ;;
    *)
        echo "Usage: $0 {backup|restore <backup_file>}"
        exit 1
        ;;
esac

exit 0