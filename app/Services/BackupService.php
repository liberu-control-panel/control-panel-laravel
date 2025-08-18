<?php

namespace App\Services;

use ZipArchive;
use App\Models\Backup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class BackupService
{
    public function createBackup(Backup $backup)
    {
        $backupName = 'backup_' . now()->format('Y-m-d_H-i-s') . '.zip';
        $backupPath = storage_path('app/backups/' . $backupName);

        // Create a zip file containing the database dump and the storage folder
        $zip = new ZipArchive();
        $zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add database dump
        $databaseDump = $this->getDatabaseDump();
        $zip->addFromString('database.sql', $databaseDump);

        // Add storage folder
        $storagePath = storage_path('app');
        $files = File::allFiles($storagePath);
        foreach ($files as $file) {
            $zip->addFile($file->getRealPath(), 'storage/' . $file->getRelativePathname());
        }

        $zip->close();

        // Encrypt the backup file
        $encryptedContent = Crypt::encryptString(file_get_contents($backupPath));
        file_put_contents($backupPath, $encryptedContent);

        // Update the backup record
        $backup->update([
            'last_backup_at' => now(),
            'file_path' => $backupPath,
        ]);

        // Remove old backups based on retention policy
        $this->removeOldBackups($backup);
    }

    private function getDatabaseDump()
    {
        // Implement database dump logic here
        // You may use Laravel's database configuration to get connection details
        // and use a tool like mysqldump to create the database dump
        // Return the dump as a string
    }

    private function removeOldBackups(Backup $backup)
    {
        $oldBackups = Backup::where('id', '!=', $backup->id)
            ->where('created_at', '<', now()->subDays($backup->retention_days))
            ->get();

        foreach ($oldBackups as $oldBackup) {
            if (File::exists($oldBackup->file_path)) {
                File::delete($oldBackup->file_path);
            }
            $oldBackup->delete();
        }
    }
}