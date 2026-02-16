<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Backup;
use App\Models\Domain;
use App\Models\User;
use App\Services\BulkRestoreService;
use App\Services\BackupService;
use App\Services\ExternalBackupParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BulkRestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BulkRestoreService $service;
    protected User $user;
    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BulkRestoreService::class);
        
        $this->user = User::factory()->create();
        $this->domain = Domain::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_bulk_restore_returns_results_for_all_backups()
    {
        $backups = Backup::factory()->count(3)->create([
            'domain_id' => $this->domain->id,
            'status' => Backup::STATUS_COMPLETED,
        ]);

        $backupIds = $backups->pluck('id')->toArray();
        
        $results = $this->service->bulkRestore($backupIds, ['continue_on_error' => true]);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey($backupIds[0], $results);
        $this->assertArrayHasKey($backupIds[1], $results);
        $this->assertArrayHasKey($backupIds[2], $results);
    }

    public function test_get_bulk_restore_stats()
    {
        $results = [
            1 => ['success' => true, 'backup' => null, 'error' => null],
            2 => ['success' => true, 'backup' => null, 'error' => null],
            3 => ['success' => false, 'backup' => null, 'error' => 'Test error'],
        ];

        $stats = $this->service->getBulkRestoreStats($results);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['successful']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertArrayHasKey('details', $stats);
    }

    public function test_restore_external_backup_detects_type()
    {
        // Create a mock backup archive
        $tempDir = storage_path('app/temp/test_backup_' . uniqid());
        mkdir($tempDir, 0755, true);
        
        // Create Liberu backup structure
        touch($tempDir . '/files.tar.gz');
        mkdir($tempDir . '/databases', 0755, true);

        $archivePath = storage_path('app/temp/external_backup.tar.gz');
        exec("tar -czf {$archivePath} -C " . dirname($tempDir) . " " . basename($tempDir));

        // Mock the parser to avoid actual restoration
        $parser = $this->createMock(ExternalBackupParser::class);
        $parser->expects($this->once())
            ->method('detectBackupType')
            ->with($archivePath)
            ->willReturn(ExternalBackupParser::TYPE_LIBERU);

        $this->app->instance(ExternalBackupParser::class, $parser);

        // Cleanup
        unlink($archivePath);
        exec("rm -rf {$tempDir}");
    }
}
