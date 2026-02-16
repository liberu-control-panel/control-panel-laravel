# Backup and Restore System

This document describes the comprehensive backup and restore system for the Liberu Control Panel.

## Features

- **Multiple Backup Destinations**: Support for Local, SFTP, FTP, and S3-compatible storage
- **Deployment-Aware Backups**: Automatic detection and support for Kubernetes, Docker, and Standalone deployments
- **Bulk Restore**: Restore multiple backups simultaneously
- **External Backup Formats**: Import and restore backups from cPanel, Virtualmin, and Plesk
- **Automated Backups**: Schedule automatic backups with configurable retention policies
- **Incremental Backups**: Full, files-only, database-only, and email-only backup types

## Architecture

### Components

1. **BackupService** - Core backup creation and restoration
2. **BackupDestinationService** - Manage backup storage destinations
3. **BulkRestoreService** - Handle bulk restore operations
4. **ExternalBackupParser** - Parse and import external backup formats
5. **DeploymentDetectionService** - Auto-detect deployment environment

### Models

- **Backup** - Represents a backup instance
- **BackupDestination** - Defines where backups are stored
- **Domain** - The entity being backed up

## Usage

### Creating Backup Destinations

```php
use App\Services\BackupDestinationService;
use App\Models\BackupDestination;

$destinationService = app(BackupDestinationService::class);

// Create Local Destination
$local = $destinationService->create([
    'name' => 'Local Storage',
    'type' => BackupDestination::TYPE_LOCAL,
    'is_default' => true,
    'is_active' => true,
    'configuration' => [
        'path' => storage_path('app/backups'),
    ],
    'retention_days' => 30,
]);

// Create SFTP Destination
$sftp = $destinationService->create([
    'name' => 'Remote SFTP',
    'type' => BackupDestination::TYPE_SFTP,
    'is_default' => false,
    'is_active' => true,
    'configuration' => [
        'host' => 'backup.example.com',
        'port' => 22,
        'username' => 'backup_user',
        'password' => 'secure_password',
        'root' => '/backups',
    ],
    'retention_days' => 60,
]);

// Create S3 Destination
$s3 = $destinationService->create([
    'name' => 'AWS S3',
    'type' => BackupDestination::TYPE_S3,
    'is_default' => false,
    'is_active' => true,
    'configuration' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => 'us-east-1',
        'bucket' => 'my-backups',
        'endpoint' => null, // Optional for S3-compatible services
    ],
    'retention_days' => 90,
]);

// Create FTP Destination
$ftp = $destinationService->create([
    'name' => 'FTP Server',
    'type' => BackupDestination::TYPE_FTP,
    'is_default' => false,
    'is_active' => true,
    'configuration' => [
        'host' => 'ftp.example.com',
        'port' => 21,
        'username' => 'ftp_user',
        'password' => 'secure_password',
        'root' => '/backups',
        'passive' => true,
        'ssl' => false,
    ],
    'retention_days' => 45,
]);
```

### Creating Backups

```php
use App\Services\BackupService;
use App\Models\Domain;

$backupService = app(BackupService::class);
$domain = Domain::where('domain_name', 'example.com')->first();

// Full backup (files + databases + email)
$backup = $backupService->createFullBackup($domain, [
    'name' => 'Monthly Backup',
    'description' => 'Complete monthly backup',
    'is_automated' => false,
]);

// Files-only backup
$filesBackup = $backupService->createFilesBackup($domain, [
    'name' => 'Files Backup',
]);

// Database-only backup
$dbBackup = $backupService->createDatabaseBackup($domain, [
    'name' => 'Database Backup',
]);

// Backup to specific destination
$destination = BackupDestination::where('type', BackupDestination::TYPE_S3)->first();
$s3Backup = $backupService->createBackupToDestination($domain, $destination, [
    'type' => Backup::TYPE_FULL,
    'name' => 'S3 Backup',
]);

// Deployment-aware backup (auto-detects Kubernetes/Docker/Standalone)
$deploymentBackup = $backupService->createBackupForDeployment($domain, [
    'name' => 'Auto-Deployment Backup',
]);
```

### Restoring Backups

```php
use App\Services\BackupService;
use App\Models\Backup;

$backupService = app(BackupService::class);
$backup = Backup::find(1);

// Restore backup
$success = $backupService->restoreBackup($backup, [
    'clear_existing' => false, // Whether to clear existing files
]);
```

### Bulk Restore

```php
use App\Services\BulkRestoreService;

$bulkRestoreService = app(BulkRestoreService::class);

// Restore multiple backups
$backupIds = [1, 2, 3, 4, 5];
$results = $bulkRestoreService->bulkRestore($backupIds, [
    'continue_on_error' => true,
]);

// Get statistics
$stats = $bulkRestoreService->getBulkRestoreStats($results);
// Returns: ['total' => 5, 'successful' => 4, 'failed' => 1, 'details' => [...]]
```

### Importing External Backups

```php
use App\Services\BulkRestoreService;

$bulkRestoreService = app(BulkRestoreService::class);

// Restore cPanel backup
$success = $bulkRestoreService->restoreExternalBackup('/path/to/cpanel-backup.tar.gz', [
    'domain_name' => 'example.com',
    'user_id' => 1,
    'continue_on_error' => true,
]);

// Restore Virtualmin backup
$success = $bulkRestoreService->restoreExternalBackup('/path/to/virtualmin-backup.tar.gz', [
    'domain_name' => 'example.com',
    'user_id' => 1,
]);

// Restore Plesk backup
$success = $bulkRestoreService->restoreExternalBackup('/path/to/plesk-backup.tar.gz', [
    'domain_name' => 'example.com',
    'user_id' => 1,
]);
```

### Testing Backup Destinations

```php
use App\Services\BackupDestinationService;
use App\Models\BackupDestination;

$destinationService = app(BackupDestinationService::class);
$destination = BackupDestination::find(1);

// Test connection
$isConnected = $destinationService->testConnection($destination);

if ($isConnected) {
    echo "Connection successful!";
} else {
    echo "Connection failed!";
}
```

### Cleanup Old Backups

```php
use App\Services\BackupDestinationService;
use App\Models\BackupDestination;

$destinationService = app(BackupDestinationService::class);
$destination = BackupDestination::find(1);

// Clean up backups older than retention period
$deletedCount = $destinationService->cleanupOldBackups($destination);

echo "Deleted {$deletedCount} old backups";
```

## Deployment-Specific Behavior

### Kubernetes Deployments

For Kubernetes deployments, backups are created by:
1. Executing tar commands inside the pod to create archives
2. Copying the archives from the pod to the control panel storage
3. Uploading to the configured backup destination

Restores work similarly in reverse.

### Docker Compose Deployments

For Docker deployments, backups are created by:
1. Executing tar commands inside the container
2. Copying files from the container to the host
3. Uploading to the configured backup destination

### Standalone Deployments

For standalone servers, backups are created by:
1. Directly creating tar archives from the file system
2. Uploading to the configured backup destination

## Supported External Backup Formats

### cPanel Backups

- Structure: `homedir.tar`, `mysql/*.sql`, `mail/`
- Supports: Files, databases, email accounts
- Auto-detection: Presence of `cp/` or `homedir.tar`

### Virtualmin Backups

- Structure: `virtualmin.info`, `public_html.tar.gz`, `mysql/*.sql`, `pgsql/*.sql`
- Supports: Files, MySQL/PostgreSQL databases, email
- Auto-detection: Presence of `virtualmin.info` or `domain.info`

### Plesk Backups

- Structure: `dump.xml`, `clients/*/domains/*/httpdocs/`, `databases/*.sql`
- Supports: Files, databases
- Auto-detection: Presence of `dump.xml` or `psa_`

## Environment Variables

Add these to your `.env` file for backup destinations:

```env
# SFTP Configuration
SFTP_HOST=backup.example.com
SFTP_PORT=22
SFTP_USERNAME=backup_user
SFTP_PASSWORD=secure_password
SFTP_ROOT=/backups
SFTP_TIMEOUT=30

# FTP Configuration
FTP_HOST=ftp.example.com
FTP_PORT=21
FTP_USERNAME=ftp_user
FTP_PASSWORD=secure_password
FTP_ROOT=/backups
FTP_PASSIVE=true
FTP_SSL=false
FTP_TIMEOUT=30

# S3 Configuration (Already exists in Laravel)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-backups
AWS_ENDPOINT=null  # For S3-compatible services like MinIO
AWS_USE_PATH_STYLE_ENDPOINT=false
```

## Database Schema

### backups table

- `id` - Primary key
- `domain_id` - Foreign key to domains
- `destination_id` - Foreign key to backup_destinations (nullable)
- `type` - Backup type (full, files, database, email)
- `name` - Backup name
- `description` - Backup description
- `file_path` - Path to backup file
- `file_size` - Size in bytes
- `status` - Status (pending, running, completed, failed)
- `started_at` - Timestamp when backup started
- `completed_at` - Timestamp when backup completed
- `error_message` - Error message if failed
- `is_automated` - Whether this is an automated backup
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

### backup_destinations table

- `id` - Primary key
- `name` - Destination name
- `type` - Type (local, sftp, ftp, s3)
- `is_default` - Whether this is the default destination
- `is_active` - Whether this destination is active
- `configuration` - JSON configuration (type-specific)
- `description` - Description
- `retention_days` - Number of days to retain backups
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

## API Endpoints

(To be implemented via Filament resources or API controllers)

- `POST /api/backup-destinations` - Create backup destination
- `GET /api/backup-destinations` - List backup destinations
- `PUT /api/backup-destinations/{id}` - Update backup destination
- `DELETE /api/backup-destinations/{id}` - Delete backup destination
- `POST /api/backup-destinations/{id}/test` - Test connection
- `POST /api/backups` - Create backup
- `GET /api/backups` - List backups
- `POST /api/backups/{id}/restore` - Restore backup
- `POST /api/backups/bulk-restore` - Bulk restore
- `POST /api/backups/import-external` - Import external backup
- `DELETE /api/backups/{id}` - Delete backup

## Security Considerations

1. **Encryption**: Backups should be encrypted when stored on remote destinations
2. **Access Control**: Only authorized users should be able to create/restore backups
3. **Credential Storage**: Backup destination credentials are stored encrypted in the database
4. **Validation**: Always validate backup integrity before restoration
5. **Audit Logging**: All backup operations should be logged for security auditing

## Best Practices

1. **Regular Backups**: Schedule automated backups daily or weekly
2. **Multiple Destinations**: Use at least two different backup destinations
3. **Test Restores**: Regularly test backup restoration to ensure reliability
4. **Retention Policy**: Configure appropriate retention periods based on storage capacity
5. **Monitor Space**: Regularly check available storage space on backup destinations
6. **Offsite Backups**: Use cloud storage (S3, etc.) for disaster recovery
7. **Documentation**: Document your backup and restore procedures

## Troubleshooting

### Backup Fails with "Permission Denied"

Ensure the web server user has write permissions to the backup directory.

```bash
sudo chown -R www-data:www-data /path/to/backup/directory
sudo chmod -R 755 /path/to/backup/directory
```

### SFTP Connection Fails

1. Verify SSH key permissions (should be 600)
2. Check firewall rules for port 22
3. Test connection manually: `sftp user@host`

### S3 Upload Fails

1. Verify AWS credentials are correct
2. Check bucket permissions and policies
3. Ensure the bucket exists in the specified region

### Kubernetes Backup Fails

1. Verify kubectl is installed and configured
2. Check pod is running: `kubectl get pods -n namespace`
3. Verify RBAC permissions for backup operations

## License

This backup system is part of the Liberu Control Panel and is licensed under the MIT License.
