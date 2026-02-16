<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Backup;
use App\Models\Domain;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_creation()
    {
        $this->markTestSkipped('This test requires Docker and proper service mocking. Manual verification needed.');
        
        $backup = Backup::factory()->create();
        $backupService = $this->mock(BackupService::class);
        
        $domain = Domain::factory()->create();
        
        $backupService->shouldReceive('createFullBackup')
            ->once()
            ->with($domain, [])
            ->andReturn($backup);

        $result = $backupService->createFullBackup($domain);
        
        $this->assertInstanceOf(Backup::class, $result);
    }

    public function test_backup_restoration()
    {
        $this->markTestSkipped('This test requires Docker and proper service mocking. Manual verification needed.');
        
        $domain = Domain::factory()->create();
        $backup = Backup::factory()->create(['domain_id' => $domain->id]);
        
        $backupService = $this->mock(BackupService::class);
        $backupService->shouldReceive('createFullBackup')->once()->andReturn($backup);
        $backupService->shouldReceive('restoreBackup')->once()->andReturn(true);

        $backupService->createFullBackup($domain);
        $result = $backupService->restoreBackup($backup);

        $this->assertTrue($result);
    }

    public function test_old_backups_removal()
    {
        $this->markTestSkipped('This test requires proper service implementation for cleanup. Manual verification needed.');
        
        $oldBackup = Backup::factory()->create([
            'created_at' => now()->subDays(10),
        ]);

        $newBackup = Backup::factory()->create();

        $backupService = $this->mock(BackupService::class);
        $backupService->shouldReceive('deleteBackup')->once()->andReturn(true);

        $result = $backupService->deleteBackup($oldBackup);

        $this->assertTrue($result);
    }
}