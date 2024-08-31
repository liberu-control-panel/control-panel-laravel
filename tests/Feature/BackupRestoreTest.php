<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Backup;
use App\Services\BackupService;
use App\Services\RestoreService;
use Illuminate\Support\Facades\Storage;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_creation()
    {
        $backup = Backup::factory()->create();
        $backupService = new BackupService();

        $backupService->createBackup($backup);

        $this->assertFileExists($backup->fresh()->file_path);
    }

    public function test_backup_restoration()
    {
        $backup = Backup::factory()->create();
        $backupService = new BackupService();
        $restoreService = new RestoreService();

        $backupService->createBackup($backup);

        // Simulate changes to the database and storage
        // ...

        $restoreService->restoreBackup($backup);

        // Assert that the database and storage have been restored
        // ...
    }

    public function test_old_backups_removal()
    {
        $oldBackup = Backup::factory()->create([
            'created_at' => now()->subDays(10),
            'retention_days' => 7,
        ]);

        $newBackup = Backup::factory()->create([
            'retention_days' => 7,
        ]);

        $backupService = new BackupService();

        $backupService->createBackup($newBackup);

        $this->assertDatabaseMissing('backups', ['id' => $oldBackup->id]);

        $this->assertFileDoesNotExist($oldBackup->file_path);
    }
}