# Backup Scripts

Automated backup solution for the Liberu Control Panel.

## Backup Script

**Location**: `scripts/backup/backup.sh`

### Features

- **Database Backup**: Supports MySQL/MariaDB and PostgreSQL
- **Application Backup**: Full application code (excluding vendor/node_modules)
- **Storage Backup**: User uploads and application storage
- **Configuration Backup**: Environment files and system configurations
- **Compression**: Automatic gzip compression
- **Retention**: Automatic cleanup of old backups
- **S3 Upload**: Optional upload to AWS S3
- **Notifications**: Email and webhook notifications

### Installation

```bash
# Make script executable
chmod +x scripts/backup/backup.sh

# Create backup directory
sudo mkdir -p /var/backups/control-panel
sudo chown www-data:www-data /var/backups/control-panel
```

### Manual Backup

```bash
# Run backup manually
sudo -u www-data ./scripts/backup/backup.sh

# Or with custom configuration
sudo BACKUP_DIR=/custom/path RETENTION_DAYS=14 ./scripts/backup/backup.sh
```

### Automated Backups (Cron)

Add to crontab for automated backups:

```bash
# Edit www-data crontab
sudo crontab -u www-data -e

# Add daily backup at 2 AM
0 2 * * * /var/www/control-panel/scripts/backup/backup.sh >> /var/log/control-panel-backup.log 2>&1

# Or weekly backup on Sundays at 3 AM
0 3 * * 0 /var/www/control-panel/scripts/backup/backup.sh >> /var/log/control-panel-backup.log 2>&1
```

### Automated Backups (Systemd Timer)

Create systemd timer for more control:

```bash
# Create service file
sudo tee /etc/systemd/system/control-panel-backup.service << 'EOF'
[Unit]
Description=Control Panel Backup
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/control-panel
ExecStart=/var/www/control-panel/scripts/backup/backup.sh
StandardOutput=journal
StandardError=journal
SyslogIdentifier=control-panel-backup
EOF

# Create timer file
sudo tee /etc/systemd/system/control-panel-backup.timer << 'EOF'
[Unit]
Description=Run Control Panel Backup Daily
Requires=control-panel-backup.service

[Timer]
OnCalendar=daily
OnCalendar=02:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

# Enable and start timer
sudo systemctl daemon-reload
sudo systemctl enable control-panel-backup.timer
sudo systemctl start control-panel-backup.timer

# Check timer status
sudo systemctl list-timers control-panel-backup.timer
```

## Configuration

### Environment Variables

Set these in your environment or `.env` file:

```bash
# Backup directory (default: /var/backups/control-panel)
BACKUP_DIR=/custom/backup/path

# Application directory (default: /var/www/control-panel)
APP_DIR=/var/www/control-panel

# Retention period in days (default: 7)
RETENTION_DAYS=14

# S3 upload (optional)
AWS_S3_BACKUP_BUCKET=my-backup-bucket

# Notifications (optional)
BACKUP_NOTIFICATION_EMAIL=admin@example.com
BACKUP_WEBHOOK_URL=https://hooks.example.com/backup
```

### Database Credentials

The script reads database credentials from `.env`:
- `DB_CONNECTION` (mysql or pgsql)
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## Backup Contents

Each backup includes:

1. **database.sql.gz**: Compressed database dump
2. **application.tar.gz**: Application code (excluding dependencies)
3. **storage.tar.gz**: Storage directory (user files, logs)
4. **configs.tar.gz**: Configuration files (.env, nginx, etc.)
5. **manifest.txt**: Backup metadata and file listing

## S3 Upload (Optional)

### Setup AWS CLI

```bash
# Install AWS CLI
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Configure credentials
aws configure
```

### Configure S3 Bucket

```bash
# Create S3 bucket
aws s3 mb s3://my-control-panel-backups

# Enable versioning
aws s3api put-bucket-versioning \
    --bucket my-control-panel-backups \
    --versioning-configuration Status=Enabled

# Set lifecycle policy (optional - auto-delete old backups)
cat > lifecycle-policy.json << 'EOF'
{
  "Rules": [
    {
      "Id": "DeleteOldBackups",
      "Status": "Enabled",
      "Prefix": "backups/",
      "Expiration": {
        "Days": 90
      }
    }
  ]
}
EOF

aws s3api put-bucket-lifecycle-configuration \
    --bucket my-control-panel-backups \
    --lifecycle-configuration file://lifecycle-policy.json
```

### Enable S3 Upload

Add to `.env` or backup script environment:
```bash
AWS_S3_BACKUP_BUCKET=my-control-panel-backups
```

## Restore from Backup

### 1. Extract Backup

```bash
# List available backups
ls -lh /var/backups/control-panel/

# Extract backup
cd /tmp
tar -xzf /var/backups/control-panel/control-panel-backup-20260216_020000.tar.gz
cd control-panel-backup-20260216_020000
```

### 2. Restore Database

```bash
# MySQL/MariaDB
gunzip < database.sql.gz | mysql -u root -p database_name

# PostgreSQL
gunzip < database.sql.gz | psql -U postgres database_name
```

### 3. Restore Application Files

```bash
# Backup current installation
sudo mv /var/www/control-panel /var/www/control-panel.old

# Extract application
sudo tar -xzf application.tar.gz -C /var/www/

# Set permissions
sudo chown -R www-data:www-data /var/www/control-panel
```

### 4. Restore Storage

```bash
# Extract storage
sudo tar -xzf storage.tar.gz -C /var/www/control-panel/

# Set permissions
sudo chown -R www-data:www-data /var/www/control-panel/storage
sudo chmod -R 775 /var/www/control-panel/storage
```

### 5. Restore Configurations

```bash
# Extract configs
sudo tar -xzf configs.tar.gz -C /

# Verify .env file
sudo cat /var/www/control-panel/.env
```

### 6. Finalize Restore

```bash
# Clear caches
cd /var/www/control-panel
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan view:clear

# Restart services
sudo systemctl restart nginx php8.3-fpm
sudo systemctl restart control-panel-queue
```

## Monitoring

### Check Backup Status

```bash
# List recent backups
ls -lht /var/backups/control-panel/ | head -n 10

# Check backup sizes
du -sh /var/backups/control-panel/*

# View backup manifest
tar -xzf backup.tar.gz control-panel-backup-*/manifest.txt -O
```

### View Backup Logs

```bash
# Systemd journal
sudo journalctl -u control-panel-backup -n 50

# Cron logs
sudo tail -f /var/log/control-panel-backup.log

# System logs
sudo grep backup /var/log/syslog
```

### Test Backup Integrity

```bash
# Test tar file integrity
tar -tzf /var/backups/control-panel/backup.tar.gz > /dev/null

# Test database backup
gunzip < database.sql.gz | head -n 100
```

## Troubleshooting

### Backup Script Fails

1. Check permissions: `ls -la /var/backups/control-panel`
2. Check disk space: `df -h`
3. Verify database credentials in `.env`
4. Check logs for specific errors

### Database Backup Fails

```bash
# Test database connection
mysql -h localhost -u username -p database_name -e "SELECT 1"

# Check mysqldump is available
which mysqldump
```

### S3 Upload Fails

```bash
# Test AWS CLI
aws s3 ls s3://my-backup-bucket

# Check credentials
aws sts get-caller-identity

# Test upload manually
aws s3 cp test.txt s3://my-backup-bucket/
```

### Insufficient Disk Space

```bash
# Check disk usage
df -h

# Find large files
du -sh /var/backups/control-panel/* | sort -hr | head

# Clean old backups manually
find /var/backups/control-panel -name "*.tar.gz" -mtime +30 -delete
```

## Best Practices

1. **Test restores regularly** - Verify backups are valid
2. **Multiple backup locations** - Local + S3 or other cloud storage
3. **Monitor backup success** - Set up alerts for failures
4. **Secure backup storage** - Encrypt sensitive backups
5. **Document restore procedures** - Keep instructions accessible
6. **Automate backups** - Use cron or systemd timers
7. **Off-site backups** - Store copies off-premises for disaster recovery
8. **Version control configs** - Keep .env and configs in version control (encrypted)

## Security Considerations

- Backup files contain sensitive data (database, .env)
- Secure backup directory permissions (700 or 750)
- Encrypt backups if storing off-site
- Use secure transfer methods (SFTP, S3 with encryption)
- Rotate backup encryption keys periodically
- Limit access to backup storage
- Audit backup access logs

## Retention Policy Recommendations

- **Daily backups**: Keep for 7 days
- **Weekly backups**: Keep for 4 weeks
- **Monthly backups**: Keep for 12 months
- **Yearly backups**: Keep for 3-7 years (compliance dependent)
