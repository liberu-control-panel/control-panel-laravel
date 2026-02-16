<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ExternalBackupParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class ExternalBackupParserTest extends TestCase
{
    use RefreshDatabase;

    protected ExternalBackupParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(ExternalBackupParser::class);
    }

    public function test_detect_liberu_backup_type()
    {
        // Create a mock Liberu backup archive
        $tempDir = storage_path('app/temp/test_backup_' . uniqid());
        mkdir($tempDir, 0755, true);
        
        // Create expected file structure
        touch($tempDir . '/files.tar.gz');
        mkdir($tempDir . '/databases', 0755, true);
        touch($tempDir . '/databases/test_db.sql');

        // Create archive
        $archivePath = storage_path('app/temp/liberu_backup.tar.gz');
        exec("tar -czf {$archivePath} -C " . dirname($tempDir) . " " . basename($tempDir));

        $type = $this->parser->detectBackupType($archivePath);

        $this->assertEquals(ExternalBackupParser::TYPE_LIBERU, $type);

        // Cleanup
        unlink($archivePath);
        File::deleteDirectory($tempDir);
    }

    public function test_parse_liberu_backup()
    {
        // Create a mock Liberu backup
        $tempDir = storage_path('app/temp/test_backup_' . uniqid());
        mkdir($tempDir, 0755, true);
        
        file_put_contents($tempDir . '/files.tar.gz', 'mock files');
        mkdir($tempDir . '/databases', 0755, true);
        file_put_contents($tempDir . '/databases/test_db.sql', 'CREATE TABLE test;');
        file_put_contents($tempDir . '/email.tar.gz', 'mock email');

        $archivePath = storage_path('app/temp/liberu_backup.tar.gz');
        exec("tar -czf {$archivePath} -C " . dirname($tempDir) . " " . basename($tempDir));

        $parsed = $this->parser->parseLiberuBackup($archivePath);

        $this->assertEquals(ExternalBackupParser::TYPE_LIBERU, $parsed['type']);
        $this->assertArrayHasKey('files_archive', $parsed);
        $this->assertArrayHasKey('databases_dir', $parsed);
        $this->assertArrayHasKey('email_archive', $parsed);

        // Cleanup
        unlink($archivePath);
        File::deleteDirectory($tempDir);
    }

    public function test_detect_unknown_backup_throws_exception()
    {
        // Create a backup with unknown structure
        $tempDir = storage_path('app/temp/test_backup_' . uniqid());
        mkdir($tempDir, 0755, true);
        touch($tempDir . '/unknown_file.txt');

        $archivePath = storage_path('app/temp/unknown_backup.tar.gz');
        exec("tar -czf {$archivePath} -C " . dirname($tempDir) . " " . basename($tempDir));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown backup format');

        $this->parser->detectBackupType($archivePath);

        // Cleanup
        unlink($archivePath);
        File::deleteDirectory($tempDir);
    }
}
