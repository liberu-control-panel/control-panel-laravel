<?php

namespace App\Services;

use App\Models\BackupDestination;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class BackupDestinationService
{
    /**
     * Create a new backup destination
     */
    public function create(array $data): BackupDestination
    {
        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            BackupDestination::where('is_default', true)->update(['is_default' => false]);
        }

        $destination = BackupDestination::create($data);

        // Register the filesystem disk
        $this->registerFilesystemDisk($destination);

        return $destination;
    }

    /**
     * Update a backup destination
     */
    public function update(BackupDestination $destination, array $data): BackupDestination
    {
        // If this is set as default, unset other defaults
        if (isset($data['is_default']) && $data['is_default']) {
            BackupDestination::where('id', '!=', $destination->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $destination->update($data);

        // Re-register the filesystem disk
        $this->registerFilesystemDisk($destination);

        return $destination;
    }

    /**
     * Delete a backup destination
     */
    public function delete(BackupDestination $destination): bool
    {
        if ($destination->is_default) {
            throw new Exception('Cannot delete the default backup destination');
        }

        if ($destination->backups()->exists()) {
            throw new Exception('Cannot delete a destination that has backups');
        }

        return $destination->delete();
    }

    /**
     * Test connection to a backup destination
     */
    public function testConnection(BackupDestination $destination): bool
    {
        try {
            $disk = $this->getDisk($destination);
            
            // Try to write a test file
            $testFile = 'test_' . time() . '.txt';
            $disk->put($testFile, 'Connection test');
            
            // Verify file exists
            $exists = $disk->exists($testFile);
            
            // Clean up
            $disk->delete($testFile);
            
            return $exists;
        } catch (Exception $e) {
            Log::error("Backup destination connection test failed for {$destination->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the storage disk for a destination
     */
    public function getDisk(BackupDestination $destination)
    {
        $diskName = $destination->getDiskName();
        
        // Register the disk if not already registered
        if (!Config::has("filesystems.disks.{$diskName}")) {
            $this->registerFilesystemDisk($destination);
        }
        
        return Storage::disk($diskName);
    }

    /**
     * Register a filesystem disk for a destination
     */
    protected function registerFilesystemDisk(BackupDestination $destination): void
    {
        $diskName = $destination->getDiskName();
        $config = $this->buildDiskConfig($destination);
        
        Config::set("filesystems.disks.{$diskName}", $config);
    }

    /**
     * Build filesystem disk configuration
     */
    protected function buildDiskConfig(BackupDestination $destination): array
    {
        $baseConfig = match ($destination->type) {
            BackupDestination::TYPE_LOCAL => $this->buildLocalConfig($destination),
            BackupDestination::TYPE_SFTP => $this->buildSftpConfig($destination),
            BackupDestination::TYPE_FTP => $this->buildFtpConfig($destination),
            BackupDestination::TYPE_S3 => $this->buildS3Config($destination),
            default => throw new Exception("Unknown destination type: {$destination->type}"),
        };

        return array_merge($baseConfig, ['throw' => false]);
    }

    /**
     * Build local storage configuration
     */
    protected function buildLocalConfig(BackupDestination $destination): array
    {
        $path = $destination->getConfigValue('path', storage_path('app/backups'));
        
        return [
            'driver' => 'local',
            'root' => $path,
        ];
    }

    /**
     * Build SFTP configuration
     */
    protected function buildSftpConfig(BackupDestination $destination): array
    {
        return [
            'driver' => 'sftp',
            'host' => $destination->getConfigValue('host'),
            'username' => $destination->getConfigValue('username'),
            'password' => $destination->getConfigValue('password'),
            'privateKey' => $destination->getConfigValue('private_key'),
            'passphrase' => $destination->getConfigValue('passphrase'),
            'port' => $destination->getConfigValue('port', 22),
            'root' => $destination->getConfigValue('root', '/'),
            'timeout' => $destination->getConfigValue('timeout', 30),
        ];
    }

    /**
     * Build FTP configuration
     */
    protected function buildFtpConfig(BackupDestination $destination): array
    {
        return [
            'driver' => 'ftp',
            'host' => $destination->getConfigValue('host'),
            'username' => $destination->getConfigValue('username'),
            'password' => $destination->getConfigValue('password'),
            'port' => $destination->getConfigValue('port', 21),
            'root' => $destination->getConfigValue('root', '/'),
            'passive' => $destination->getConfigValue('passive', true),
            'ssl' => $destination->getConfigValue('ssl', false),
            'timeout' => $destination->getConfigValue('timeout', 30),
        ];
    }

    /**
     * Build S3 configuration
     */
    protected function buildS3Config(BackupDestination $destination): array
    {
        return [
            'driver' => 's3',
            'key' => $destination->getConfigValue('key'),
            'secret' => $destination->getConfigValue('secret'),
            'region' => $destination->getConfigValue('region'),
            'bucket' => $destination->getConfigValue('bucket'),
            'url' => $destination->getConfigValue('url'),
            'endpoint' => $destination->getConfigValue('endpoint'),
            'use_path_style_endpoint' => $destination->getConfigValue('use_path_style_endpoint', false),
        ];
    }

    /**
     * Get default backup destination
     */
    public function getDefault(): ?BackupDestination
    {
        return BackupDestination::default()->active()->first();
    }

    /**
     * Upload a file to a destination
     */
    public function uploadFile(BackupDestination $destination, string $localPath, string $remotePath): bool
    {
        try {
            $disk = $this->getDisk($destination);
            $contents = file_get_contents($localPath);
            return $disk->put($remotePath, $contents);
        } catch (Exception $e) {
            Log::error("Failed to upload file to {$destination->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Download a file from a destination
     */
    public function downloadFile(BackupDestination $destination, string $remotePath, string $localPath): bool
    {
        try {
            $disk = $this->getDisk($destination);
            $contents = $disk->get($remotePath);
            return file_put_contents($localPath, $contents) !== false;
        } catch (Exception $e) {
            Log::error("Failed to download file from {$destination->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file from a destination
     */
    public function deleteFile(BackupDestination $destination, string $remotePath): bool
    {
        try {
            $disk = $this->getDisk($destination);
            return $disk->delete($remotePath);
        } catch (Exception $e) {
            Log::error("Failed to delete file from {$destination->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List files in a destination
     */
    public function listFiles(BackupDestination $destination, string $directory = ''): array
    {
        try {
            $disk = $this->getDisk($destination);
            return $disk->files($directory);
        } catch (Exception $e) {
            Log::error("Failed to list files in {$destination->name}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available storage space (if supported)
     */
    public function getAvailableSpace(BackupDestination $destination): ?int
    {
        if ($destination->type !== BackupDestination::TYPE_LOCAL) {
            return null; // Not supported for remote destinations
        }

        try {
            $path = $destination->getConfigValue('path', storage_path('app/backups'));
            return disk_free_space($path);
        } catch (Exception $e) {
            Log::error("Failed to get available space for {$destination->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean up old backups based on retention policy
     */
    public function cleanupOldBackups(BackupDestination $destination): int
    {
        $deleted = 0;
        $cutoffDate = now()->subDays($destination->retention_days);

        $oldBackups = $destination->backups()
            ->where('completed_at', '<', $cutoffDate)
            ->where('status', 'completed')
            ->get();

        foreach ($oldBackups as $backup) {
            try {
                // Delete from destination
                if ($backup->file_path) {
                    $this->deleteFile($destination, basename($backup->file_path));
                }

                // Delete record
                $backup->delete();
                $deleted++;
            } catch (Exception $e) {
                Log::error("Failed to delete old backup {$backup->id}: " . $e->getMessage());
            }
        }

        return $deleted;
    }
}
