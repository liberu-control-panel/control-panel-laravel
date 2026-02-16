<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\BackupDestination;
use App\Services\BackupDestinationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class BackupDestinationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BackupDestinationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BackupDestinationService::class);
    }

    public function test_create_local_destination()
    {
        $data = [
            'name' => 'Local Backup',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => true,
            'is_active' => true,
            'configuration' => [
                'path' => storage_path('app/backups'),
            ],
            'retention_days' => 30,
        ];

        $destination = $this->service->create($data);

        $this->assertInstanceOf(BackupDestination::class, $destination);
        $this->assertEquals('Local Backup', $destination->name);
        $this->assertEquals(BackupDestination::TYPE_LOCAL, $destination->type);
        $this->assertTrue($destination->is_default);
        $this->assertTrue($destination->is_active);
    }

    public function test_create_sftp_destination()
    {
        $data = [
            'name' => 'SFTP Backup',
            'type' => BackupDestination::TYPE_SFTP,
            'is_default' => false,
            'is_active' => true,
            'configuration' => [
                'host' => 'sftp.example.com',
                'port' => 22,
                'username' => 'backup_user',
                'password' => 'secure_password',
                'root' => '/backups',
            ],
            'retention_days' => 60,
        ];

        $destination = $this->service->create($data);

        $this->assertInstanceOf(BackupDestination::class, $destination);
        $this->assertEquals('SFTP Backup', $destination->name);
        $this->assertEquals(BackupDestination::TYPE_SFTP, $destination->type);
        $this->assertEquals('sftp.example.com', $destination->getConfigValue('host'));
    }

    public function test_create_s3_destination()
    {
        $data = [
            'name' => 'S3 Backup',
            'type' => BackupDestination::TYPE_S3,
            'is_default' => false,
            'is_active' => true,
            'configuration' => [
                'key' => 'AWS_ACCESS_KEY',
                'secret' => 'AWS_SECRET_KEY',
                'region' => 'us-east-1',
                'bucket' => 'my-backups',
            ],
            'retention_days' => 90,
        ];

        $destination = $this->service->create($data);

        $this->assertInstanceOf(BackupDestination::class, $destination);
        $this->assertEquals('S3 Backup', $destination->name);
        $this->assertEquals(BackupDestination::TYPE_S3, $destination->type);
        $this->assertEquals('my-backups', $destination->getConfigValue('bucket'));
    }

    public function test_only_one_default_destination()
    {
        $destination1 = $this->service->create([
            'name' => 'First Default',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => true,
            'is_active' => true,
            'configuration' => ['path' => storage_path('app/backups1')],
            'retention_days' => 30,
        ]);

        $destination2 = $this->service->create([
            'name' => 'Second Default',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => true,
            'is_active' => true,
            'configuration' => ['path' => storage_path('app/backups2')],
            'retention_days' => 30,
        ]);

        $destination1->refresh();

        $this->assertFalse($destination1->is_default);
        $this->assertTrue($destination2->is_default);
    }

    public function test_update_destination()
    {
        $destination = $this->service->create([
            'name' => 'Test Backup',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => false,
            'is_active' => true,
            'configuration' => ['path' => storage_path('app/backups')],
            'retention_days' => 30,
        ]);

        $updated = $this->service->update($destination, [
            'name' => 'Updated Backup',
            'retention_days' => 60,
        ]);

        $this->assertEquals('Updated Backup', $updated->name);
        $this->assertEquals(60, $updated->retention_days);
    }

    public function test_get_default_destination()
    {
        $this->service->create([
            'name' => 'Non-Default',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => false,
            'is_active' => true,
            'configuration' => ['path' => storage_path('app/backups1')],
            'retention_days' => 30,
        ]);

        $default = $this->service->create([
            'name' => 'Default',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => true,
            'is_active' => true,
            'configuration' => ['path' => storage_path('app/backups2')],
            'retention_days' => 30,
        ]);

        $result = $this->service->getDefault();

        $this->assertNotNull($result);
        $this->assertEquals($default->id, $result->id);
    }

    public function test_cannot_delete_default_destination()
    {
        $destination = $this->service->create([
            'name' => 'Default',
            'type' => BackupDestination::TYPE_LOCAL,
            'is_default' => true,
            'is_active' => true,
            'configuration' => ['path' => storage_path('app/backups')],
            'retention_days' => 30,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the default backup destination');

        $this->service->delete($destination);
    }

    public function test_validate_configuration()
    {
        $localDestination = BackupDestination::factory()->create([
            'type' => BackupDestination::TYPE_LOCAL,
            'configuration' => ['path' => '/some/path'],
        ]);
        $this->assertTrue($localDestination->validateConfiguration());

        $sftpDestination = BackupDestination::factory()->create([
            'type' => BackupDestination::TYPE_SFTP,
            'configuration' => [
                'host' => 'sftp.example.com',
                'port' => 22,
                'username' => 'user',
            ],
        ]);
        $this->assertTrue($sftpDestination->validateConfiguration());

        $invalidDestination = BackupDestination::factory()->create([
            'type' => BackupDestination::TYPE_S3,
            'configuration' => ['key' => 'only_key'], // Missing required fields
        ]);
        $this->assertFalse($invalidDestination->validateConfiguration());
    }
}
