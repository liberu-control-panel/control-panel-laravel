<?php

namespace App\Services;

use Exception;
use App\Models\Domain;
use App\Models\Backup;
use App\Models\Database;
use App\Models\BackupDestination;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupService
{
    protected $containerManager;
    protected $databaseService;
    protected $fileManagerService;
    protected $backupDestinationService;
    protected $deploymentDetection;

    public function __construct(
        ContainerManagerService $containerManager,
        DatabaseService $databaseService,
        FileManagerService $fileManagerService,
        BackupDestinationService $backupDestinationService,
        DeploymentDetectionService $deploymentDetection
    ) {
        $this->containerManager = $containerManager;
        $this->databaseService = $databaseService;
        $this->fileManagerService = $fileManagerService;
        $this->backupDestinationService = $backupDestinationService;
        $this->deploymentDetection = $deploymentDetection;
    }

    /**
     * Create full backup for domain
     */
    public function createFullBackup(Domain $domain, array $options = []): ?Backup
    {
        try {
            $backup = Backup::create([
                'domain_id' => $domain->id,
                'destination_id' => $options['destination_id'] ?? null,
                'type' => Backup::TYPE_FULL,
                'name' => $options['name'] ?? "Full backup - " . now()->format('Y-m-d H:i:s'),
                'description' => $options['description'] ?? 'Automated full backup',
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'is_automated' => $options['is_automated'] ?? false
            ]);

            // Create backup directory
            $backupDir = $this->createBackupDirectory($domain, $backup);

            // Backup files
            $filesBackupPath = $backupDir . '/files.tar.gz';
            if (!$this->backupFiles($domain, $filesBackupPath)) {
                throw new Exception('Files backup failed');
            }

            // Backup databases
            $databasesBackupPath = $backupDir . '/databases';
            if (!$this->backupDatabases($domain, $databasesBackupPath)) {
                throw new Exception('Database backup failed');
            }

            // Backup email
            $emailBackupPath = $backupDir . '/email.tar.gz';
            if (!$this->backupEmail($domain, $emailBackupPath)) {
                Log::warning("Email backup failed for {$domain->domain_name}");
            }

            // Create final archive
            $finalArchivePath = $this->createFinalArchive($domain, $backup, $backupDir);

            // Calculate file size
            $fileSize = file_exists($finalArchivePath) ? filesize($finalArchivePath) : 0;

            // Update backup record
            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'completed_at' => now(),
                'file_path' => $finalArchivePath,
                'file_size' => $fileSize
            ]);

            // Clean up temporary directory
            $this->cleanupDirectory($backupDir);

            return $backup;
        } catch (Exception $e) {
            Log::error("Full backup failed for {$domain->domain_name}: " . $e->getMessage());

            if (isset($backup)) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => $e->getMessage()
                ]);
            }

            return null;
        }
    }

    /**
     * Create files-only backup
     */
    public function createFilesBackup(Domain $domain, array $options = []): ?Backup
    {
        try {
            $backup = Backup::create([
                'domain_id' => $domain->id,
                'destination_id' => $options['destination_id'] ?? null,
                'type' => Backup::TYPE_FILES,
                'name' => $options['name'] ?? "Files backup - " . now()->format('Y-m-d H:i:s'),
                'description' => $options['description'] ?? 'Files backup',
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'is_automated' => $options['is_automated'] ?? false
            ]);

            $backupPath = $this->getBackupPath($domain, $backup, 'files.tar.gz');

            if (!$this->backupFiles($domain, $backupPath)) {
                throw new Exception('Files backup failed');
            }

            $fileSize = file_exists($backupPath) ? filesize($backupPath) : 0;

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'completed_at' => now(),
                'file_path' => $backupPath,
                'file_size' => $fileSize
            ]);

            return $backup;
        } catch (Exception $e) {
            Log::error("Files backup failed for {$domain->domain_name}: " . $e->getMessage());

            if (isset($backup)) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => $e->getMessage()
                ]);
            }

            return null;
        }
    }

    /**
     * Create database backup
     */
    public function createDatabaseBackup(Domain $domain, array $options = []): ?Backup
    {
        try {
            $backup = Backup::create([
                'domain_id' => $domain->id,
                'destination_id' => $options['destination_id'] ?? null,
                'type' => Backup::TYPE_DATABASE,
                'name' => $options['name'] ?? "Database backup - " . now()->format('Y-m-d H:i:s'),
                'description' => $options['description'] ?? 'Database backup',
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'is_automated' => $options['is_automated'] ?? false
            ]);

            $backupDir = $this->createBackupDirectory($domain, $backup);

            if (!$this->backupDatabases($domain, $backupDir)) {
                throw new Exception('Database backup failed');
            }

            // Create archive of database backups
            $archivePath = $this->getBackupPath($domain, $backup, 'databases.tar.gz');
            $this->createArchive($backupDir, $archivePath);

            $fileSize = file_exists($archivePath) ? filesize($archivePath) : 0;

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'completed_at' => now(),
                'file_path' => $archivePath,
                'file_size' => $fileSize
            ]);

            // Clean up temporary directory
            $this->cleanupDirectory($backupDir);

            return $backup;
        } catch (Exception $e) {
            Log::error("Database backup failed for {$domain->domain_name}: " . $e->getMessage());

            if (isset($backup)) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => $e->getMessage()
                ]);
            }

            return null;
        }
    }

    /**
     * Backup domain files
     */
    protected function backupFiles(Domain $domain, string $backupPath): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";

            // Create tar archive of web files
            $process = new Process([
                'docker', 'exec', $containerName,
                'tar', '-czf', '/tmp/files_backup.tar.gz', '/var/www/html'
            ]);
            $process->setTimeout(1800); // 30 minutes
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception('Failed to create files archive: ' . $process->getErrorOutput());
            }

            // Copy archive from container
            $copyProcess = new Process([
                'docker', 'cp', "{$containerName}:/tmp/files_backup.tar.gz", $backupPath
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new Exception('Failed to copy files archive: ' . $copyProcess->getErrorOutput());
            }

            // Clean up container
            $cleanupProcess = new Process([
                'docker', 'exec', $containerName, 'rm', '/tmp/files_backup.tar.gz'
            ]);
            $cleanupProcess->run();

            return true;
        } catch (Exception $e) {
            Log::error("Files backup failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup domain databases
     */
    protected function backupDatabases(Domain $domain, string $backupDir): bool
    {
        try {
            $databases = $domain->databases()->active()->get();

            if ($databases->isEmpty()) {
                return true; // No databases to backup
            }

            foreach ($databases as $database) {
                $backupFile = $backupDir . "/{$database->name}.sql";

                if (!$this->databaseService->createBackup($database, dirname($backupFile))) {
                    Log::warning("Failed to backup database {$database->name} for {$domain->domain_name}");
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error("Database backup failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup domain email
     */
    protected function backupEmail(Domain $domain, string $backupPath): bool
    {
        try {
            $dovecotContainer = "dovecot";
            $mailPath = "/var/mail/{$domain->domain_name}";

            // Check if mail directory exists
            $checkProcess = new Process([
                'docker', 'exec', $dovecotContainer, 'test', '-d', $mailPath
            ]);
            $checkProcess->run();

            if (!$checkProcess->isSuccessful()) {
                return true; // No email to backup
            }

            // Create tar archive of email
            $process = new Process([
                'docker', 'exec', $dovecotContainer,
                'tar', '-czf', '/tmp/email_backup.tar.gz', $mailPath
            ]);
            $process->setTimeout(900); // 15 minutes
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception('Failed to create email archive: ' . $process->getErrorOutput());
            }

            // Copy archive from container
            $copyProcess = new Process([
                'docker', 'cp', "{$dovecotContainer}:/tmp/email_backup.tar.gz", $backupPath
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new Exception('Failed to copy email archive: ' . $copyProcess->getErrorOutput());
            }

            // Clean up container
            $cleanupProcess = new Process([
                'docker', 'exec', $dovecotContainer, 'rm', '/tmp/email_backup.tar.gz'
            ]);
            $cleanupProcess->run();

            return true;
        } catch (Exception $e) {
            Log::error("Email backup failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore backup
     */
    public function restoreBackup(Backup $backup, array $options = []): bool
    {
        try {
            $domain = $backup->domain;

            if (!file_exists($backup->file_path)) {
                throw new Exception('Backup file not found');
            }

            // Extract backup
            $extractDir = $this->extractBackup($backup);

            // Restore based on backup type
            switch ($backup->type) {
                case Backup::TYPE_FULL:
                    $success = $this->restoreFullBackup($domain, $extractDir, $options);
                    break;
                case Backup::TYPE_FILES:
                    $success = $this->restoreFilesBackup($domain, $backup->file_path, $options);
                    break;
                case Backup::TYPE_DATABASE:
                    $success = $this->restoreDatabaseBackup($domain, $extractDir, $options);
                    break;
                default:
                    throw new Exception('Unknown backup type');
            }

            // Clean up extraction directory
            if (isset($extractDir)) {
                $this->cleanupDirectory($extractDir);
            }

            return $success;
        } catch (Exception $e) {
            Log::error("Backup restore failed for {$backup->domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore full backup
     */
    protected function restoreFullBackup(Domain $domain, string $extractDir, array $options): bool
    {
        try {
            // Restore files
            $filesArchive = $extractDir . '/files.tar.gz';
            if (file_exists($filesArchive)) {
                $this->restoreFilesBackup($domain, $filesArchive, $options);
            }

            // Restore databases
            $databasesDir = $extractDir . '/databases';
            if (is_dir($databasesDir)) {
                $this->restoreDatabaseBackup($domain, $databasesDir, $options);
            }

            // Restore email
            $emailArchive = $extractDir . '/email.tar.gz';
            if (file_exists($emailArchive)) {
                $this->restoreEmailBackup($domain, $emailArchive, $options);
            }

            return true;
        } catch (Exception $e) {
            Log::error("Full backup restore failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore files backup
     */
    protected function restoreFilesBackup(Domain $domain, string $backupPath, array $options): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";

            // Copy backup to container
            $copyProcess = new Process([
                'docker', 'cp', $backupPath, "{$containerName}:/tmp/restore_files.tar.gz"
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new Exception('Failed to copy backup to container');
            }

            // Clear existing files if requested
            if ($options['clear_existing'] ?? false) {
                $clearProcess = new Process([
                    'docker', 'exec', $containerName, 'rm', '-rf', '/var/www/html/*'
                ]);
                $clearProcess->run();
            }

            // Extract files
            $extractProcess = new Process([
                'docker', 'exec', $containerName,
                'tar', '-xzf', '/tmp/restore_files.tar.gz', '-C', '/'
            ]);
            $extractProcess->run();

            if (!$extractProcess->isSuccessful()) {
                throw new Exception('Failed to extract files: ' . $extractProcess->getErrorOutput());
            }

            // Clean up
            $cleanupProcess = new Process([
                'docker', 'exec', $containerName, 'rm', '/tmp/restore_files.tar.gz'
            ]);
            $cleanupProcess->run();

            return true;
        } catch (Exception $e) {
            Log::error("Files restore failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore database backup
     */
    protected function restoreDatabaseBackup(Domain $domain, string $backupDir, array $options): bool
    {
        try {
            $sqlFiles = glob($backupDir . '/*.sql');

            foreach ($sqlFiles as $sqlFile) {
                $databaseName = pathinfo($sqlFile, PATHINFO_FILENAME);
                $database = $domain->databases()->where('name', $databaseName)->first();

                if ($database) {
                    $this->databaseService->restoreBackup($database, $sqlFile);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error("Database restore failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore email backup
     */
    protected function restoreEmailBackup(Domain $domain, string $backupPath, array $options): bool
    {
        try {
            $dovecotContainer = "dovecot";

            // Copy backup to container
            $copyProcess = new Process([
                'docker', 'cp', $backupPath, "{$dovecotContainer}:/tmp/restore_email.tar.gz"
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new Exception('Failed to copy email backup to container');
            }

            // Extract email
            $extractProcess = new Process([
                'docker', 'exec', $dovecotContainer,
                'tar', '-xzf', '/tmp/restore_email.tar.gz', '-C', '/'
            ]);
            $extractProcess->run();

            if (!$extractProcess->isSuccessful()) {
                throw new Exception('Failed to extract email: ' . $extractProcess->getErrorOutput());
            }

            // Clean up
            $cleanupProcess = new Process([
                'docker', 'exec', $dovecotContainer, 'rm', '/tmp/restore_email.tar.gz'
            ]);
            $cleanupProcess->run();

            return true;
        } catch (Exception $e) {
            Log::error("Email restore failed for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule automated backups
     */
    public function scheduleAutomatedBackups(Domain $domain, array $schedule): bool
    {
        try {
            // This would integrate with Laravel's task scheduler
            // For now, we'll just log the schedule
            Log::info("Automated backup scheduled for {$domain->domain_name}", $schedule);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to schedule automated backups for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete backup
     */
    public function deleteBackup(Backup $backup): bool
    {
        try {
            // Delete backup file
            if ($backup->file_path && file_exists($backup->file_path)) {
                unlink($backup->file_path);
            }

            // Delete backup record
            $backup->delete();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete backup {$backup->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats(Domain $domain): array
    {
        try {
            $backups = $domain->backups;
            $totalSize = $backups->sum('file_size');
            $completedBackups = $backups->where('status', Backup::STATUS_COMPLETED);
            $failedBackups = $backups->where('status', Backup::STATUS_FAILED);

            return [
                'total_backups' => $backups->count(),
                'completed_backups' => $completedBackups->count(),
                'failed_backups' => $failedBackups->count(),
                'total_size_bytes' => $totalSize,
                'total_size_human' => $this->formatBytes($totalSize),
                'latest_backup' => $completedBackups->sortByDesc('created_at')->first(),
                'oldest_backup' => $completedBackups->sortBy('created_at')->first()
            ];
        } catch (Exception $e) {
            Log::error("Failed to get backup stats for {$domain->domain_name}: " . $e->getMessage());
            return [
                'total_backups' => 0,
                'completed_backups' => 0,
                'failed_backups' => 0,
                'total_size_bytes' => 0,
                'total_size_human' => '0 B',
                'latest_backup' => null,
                'oldest_backup' => null
            ];
        }
    }

    /**
     * Create backup directory
     */
    protected function createBackupDirectory(Domain $domain, Backup $backup): string
    {
        $backupDir = storage_path("app/backups/{$domain->domain_name}/{$backup->id}");

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        return $backupDir;
    }

    /**
     * Get backup file path
     */
    protected function getBackupPath(Domain $domain, Backup $backup, string $filename): string
    {
        $backupDir = storage_path("app/backups/{$domain->domain_name}");

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        return $backupDir . "/{$backup->id}_{$filename}";
    }

    /**
     * Create final archive
     */
    protected function createFinalArchive(Domain $domain, Backup $backup, string $sourceDir): string
    {
        $archivePath = $this->getBackupPath($domain, $backup, 'full_backup.tar.gz');

        $process = new Process([
            'tar', '-czf', $archivePath, '-C', dirname($sourceDir), basename($sourceDir)
        ]);
        $process->setTimeout(1800); // 30 minutes
        $process->run();

        return $archivePath;
    }

    /**
     * Extract backup archive
     */
    protected function extractBackup(Backup $backup): string
    {
        $extractDir = storage_path("app/temp/extract_{$backup->id}");

        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        $process = new Process([
            'tar', '-xzf', $backup->file_path, '-C', $extractDir
        ]);
        $process->run();

        return $extractDir;
    }

    /**
     * Create archive from directory
     */
    protected function createArchive(string $sourceDir, string $archivePath): bool
    {
        $process = new Process([
            'tar', '-czf', $archivePath, '-C', dirname($sourceDir), basename($sourceDir)
        ]);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Clean up directory
     */
    protected function cleanupDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $process = new Process(['rm', '-rf', $directory]);
            $process->run();
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Create backup with specific destination
     */
    public function createBackupToDestination(Domain $domain, BackupDestination $destination, array $options = []): ?Backup
    {
        try {
            // Set destination in options
            $options['destination_id'] = $destination->id;
            
            // Create backup based on type
            $type = $options['type'] ?? Backup::TYPE_FULL;
            
            $backup = match ($type) {
                Backup::TYPE_FULL => $this->createFullBackup($domain, $options),
                Backup::TYPE_FILES => $this->createFilesBackup($domain, $options),
                Backup::TYPE_DATABASE => $this->createDatabaseBackup($domain, $options),
                default => throw new Exception("Unknown backup type: {$type}"),
            };

            if (!$backup) {
                return null;
            }

            // Upload to destination if not local
            if ($destination->type !== BackupDestination::TYPE_LOCAL) {
                $this->uploadBackupToDestination($backup, $destination);
            }

            return $backup;
        } catch (Exception $e) {
            Log::error("Backup to destination failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload backup to destination
     */
    protected function uploadBackupToDestination(Backup $backup, BackupDestination $destination): bool
    {
        try {
            if (!file_exists($backup->file_path)) {
                throw new Exception('Backup file not found');
            }

            $remotePath = $this->getRemoteBackupPath($backup);
            
            $success = $this->backupDestinationService->uploadFile(
                $destination,
                $backup->file_path,
                $remotePath
            );

            if ($success) {
                // Update backup with remote path
                $backup->update([
                    'file_path' => $remotePath,
                    'destination_id' => $destination->id,
                ]);
            }

            return $success;
        } catch (Exception $e) {
            Log::error("Failed to upload backup to destination: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get remote backup path
     */
    protected function getRemoteBackupPath(Backup $backup): string
    {
        $domain = $backup->domain;
        $timestamp = $backup->created_at->format('Y-m-d_H-i-s');
        return "backups/{$domain->domain_name}/{$backup->type}_{$timestamp}_{$backup->id}.tar.gz";
    }

    /**
     * Create backup based on deployment mode
     */
    public function createBackupForDeployment(Domain $domain, array $options = []): ?Backup
    {
        $deploymentMode = $this->deploymentDetection->detectDeploymentMode();
        
        Log::info("Creating backup for deployment mode: {$deploymentMode}");

        return match ($deploymentMode) {
            DeploymentDetectionService::MODE_KUBERNETES => $this->createKubernetesBackup($domain, $options),
            DeploymentDetectionService::MODE_DOCKER_COMPOSE => $this->createFullBackup($domain, $options),
            DeploymentDetectionService::MODE_STANDALONE => $this->createStandaloneBackup($domain, $options),
            default => $this->createFullBackup($domain, $options),
        };
    }

    /**
     * Create Kubernetes backup
     */
    protected function createKubernetesBackup(Domain $domain, array $options = []): ?Backup
    {
        try {
            $backup = Backup::create([
                'domain_id' => $domain->id,
                'destination_id' => $options['destination_id'] ?? null,
                'type' => Backup::TYPE_FULL,
                'name' => $options['name'] ?? "Kubernetes backup - " . now()->format('Y-m-d H:i:s'),
                'description' => $options['description'] ?? 'Kubernetes deployment backup',
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'is_automated' => $options['is_automated'] ?? false
            ]);

            $backupDir = $this->createBackupDirectory($domain, $backup);
            $namespace = "hosting-{$domain->domain_name}";
            $podName = "{$domain->domain_name}-web";

            // Backup files from Kubernetes pod
            $filesBackupPath = $backupDir . '/files.tar.gz';
            if (!$this->backupKubernetesFiles($namespace, $podName, $filesBackupPath)) {
                throw new Exception('Kubernetes files backup failed');
            }

            // Backup databases
            $databasesBackupPath = $backupDir . '/databases';
            if (!$this->backupDatabases($domain, $databasesBackupPath)) {
                throw new Exception('Database backup failed');
            }

            // Create final archive
            $finalArchivePath = $this->createFinalArchive($domain, $backup, $backupDir);
            $fileSize = file_exists($finalArchivePath) ? filesize($finalArchivePath) : 0;

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'completed_at' => now(),
                'file_path' => $finalArchivePath,
                'file_size' => $fileSize
            ]);

            $this->cleanupDirectory($backupDir);

            return $backup;
        } catch (Exception $e) {
            Log::error("Kubernetes backup failed: " . $e->getMessage());
            
            if (isset($backup)) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => $e->getMessage()
                ]);
            }

            return null;
        }
    }

    /**
     * Backup files from Kubernetes pod
     */
    protected function backupKubernetesFiles(string $namespace, string $podName, string $backupPath): bool
    {
        try {
            // Create archive in pod
            $process = new Process([
                'kubectl', 'exec', '-n', $namespace, $podName, '--',
                'tar', '-czf', '/tmp/files_backup.tar.gz', '/var/www/html'
            ]);
            $process->setTimeout(1800);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception('Failed to create archive in pod: ' . $process->getErrorOutput());
            }

            // Copy archive from pod
            $copyProcess = new Process([
                'kubectl', 'cp', "{$namespace}/{$podName}:/tmp/files_backup.tar.gz", $backupPath
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new Exception('Failed to copy archive from pod: ' . $copyProcess->getErrorOutput());
            }

            // Clean up pod
            $cleanupProcess = new Process([
                'kubectl', 'exec', '-n', $namespace, $podName, '--',
                'rm', '/tmp/files_backup.tar.gz'
            ]);
            $cleanupProcess->run();

            return true;
        } catch (Exception $e) {
            Log::error("Kubernetes files backup failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create standalone backup
     */
    protected function createStandaloneBackup(Domain $domain, array $options = []): ?Backup
    {
        try {
            $backup = Backup::create([
                'domain_id' => $domain->id,
                'destination_id' => $options['destination_id'] ?? null,
                'type' => Backup::TYPE_FULL,
                'name' => $options['name'] ?? "Standalone backup - " . now()->format('Y-m-d H:i:s'),
                'description' => $options['description'] ?? 'Standalone server backup',
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'is_automated' => $options['is_automated'] ?? false
            ]);

            $backupDir = $this->createBackupDirectory($domain, $backup);
            $webRoot = $options['web_root'] ?? "/var/www/{$domain->domain_name}/public_html";

            // Backup files from web root
            $filesBackupPath = $backupDir . '/files.tar.gz';
            if (!$this->backupStandaloneFiles($webRoot, $filesBackupPath)) {
                throw new Exception('Standalone files backup failed');
            }

            // Backup databases
            $databasesBackupPath = $backupDir . '/databases';
            if (!$this->backupDatabases($domain, $databasesBackupPath)) {
                throw new Exception('Database backup failed');
            }

            // Create final archive
            $finalArchivePath = $this->createFinalArchive($domain, $backup, $backupDir);
            $fileSize = file_exists($finalArchivePath) ? filesize($finalArchivePath) : 0;

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'completed_at' => now(),
                'file_path' => $finalArchivePath,
                'file_size' => $fileSize
            ]);

            $this->cleanupDirectory($backupDir);

            return $backup;
        } catch (Exception $e) {
            Log::error("Standalone backup failed: " . $e->getMessage());
            
            if (isset($backup)) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => $e->getMessage()
                ]);
            }

            return null;
        }
    }

    /**
     * Backup files from standalone server
     */
    protected function backupStandaloneFiles(string $webRoot, string $backupPath): bool
    {
        try {
            if (!is_dir($webRoot)) {
                Log::warning("Web root does not exist: {$webRoot}");
                return true; // Not a failure if no files exist
            }

            $process = new Process([
                'tar', '-czf', $backupPath, '-C', dirname($webRoot), basename($webRoot)
            ]);
            $process->setTimeout(1800);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception('Failed to create files archive: ' . $process->getErrorOutput());
            }

            return true;
        } catch (Exception $e) {
            Log::error("Standalone files backup failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Download backup from destination
     */
    public function downloadBackupFromDestination(Backup $backup): ?string
    {
        try {
            if (!$backup->destination) {
                throw new Exception('Backup has no destination');
            }

            $localPath = storage_path("app/temp/backup_{$backup->id}.tar.gz");
            
            $success = $this->backupDestinationService->downloadFile(
                $backup->destination,
                $backup->file_path,
                $localPath
            );

            return $success ? $localPath : null;
        } catch (Exception $e) {
            Log::error("Failed to download backup from destination: " . $e->getMessage());
            return null;
        }
    }
}