<?php

use App\Hosting\Backups\EncryptedFileArchive;
use App\Hosting\Backups\LocalFileBackups;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Liberu\ControlPanel\Backups\Actions\CreatePolicy;
use Liberu\ControlPanel\Backups\BackupsServiceProvider;
use Liberu\ControlPanel\Backups\Enums\RestoreStatus;
use Liberu\ControlPanel\Backups\Enums\SnapshotStatus;
use Liberu\ControlPanel\Backups\Models\BackupRestore;
use Liberu\ControlPanel\Backups\Models\BackupSnapshot;

beforeEach(function (): void {
    app()->register(BackupsServiceProvider::class);
    $this->artisan('migrate');
    $this->backupTestRoot = sys_get_temp_dir().'/hosting-backup-tests-'.Str::uuid();
    foreach (['source', 'archives', 'restores'] as $directory) {
        File::ensureDirectoryExists($this->backupTestRoot.'/'.$directory, 0700);
        chmod($this->backupTestRoot.'/'.$directory, 0700);
    }
    $this->policy = app(CreatePolicy::class)->execute(['team_id' => 'team-1', 'name' => 'Fixture files', 'storage_driver' => 'local-files-v1']);
    $this->backupKey = Str::random(64);
    config([
        'hosting-backups.archive_root' => $this->backupTestRoot.'/archives',
        'hosting-backups.restore_root' => $this->backupTestRoot.'/restores',
        'hosting-backups.keys' => ['v1' => $this->backupKey],
        'hosting-backups.sources' => ['fixture' => ['team_id' => 'team-1', 'policy_id' => $this->policy->getKey(), 'path' => $this->backupTestRoot.'/source']],
    ]);
    File::put($this->backupTestRoot.'/source/index.html', 'A real website fixture with private content.');
});

afterEach(function (): void {
    if (isset($this->backupTestRoot)) {
        File::deleteDirectory($this->backupTestRoot);
    }
});

it('encrypts, verifies and restores actual file bytes and empty directories', function (): void {
    File::ensureDirectoryExists($this->backupTestRoot.'/source/empty', 0700);
    File::put($this->backupTestRoot.'/source/.env', 'EXAMPLE=fixture-only');
    $binary = random_bytes(1024 * 1024);
    File::put($this->backupTestRoot.'/source/日本語.bin', $binary);
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    $archive = $this->backupTestRoot.'/archives/'.$snapshot->location;

    expect($snapshot->status)->toBe(SnapshotStatus::Verified)
        ->and($snapshot->checksum)->toBe(hash_file('sha256', $archive))
        ->and(fileperms($archive) & 0777)->toBe(0600)
        ->and(File::get($archive))->not->toContain('A real website fixture with private content.')
        ->and(json_encode($snapshot))->not->toContain($this->backupKey);
    $zip = new ZipArchive();
    $zip->open($archive);
    expect($zip->statName('files/index.html')['encryption_method'])->toBe(ZipArchive::EM_AES_256);
    $zip->close();

    File::put($this->backupTestRoot.'/source/index.html', 'New live content');
    $restore = app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1');
    expect($restore->status)->toBe(RestoreStatus::Completed)
        ->and(File::get($restore->target.'/index.html'))->toBe('A real website fixture with private content.')
        ->and(File::get($restore->target.'/日本語.bin'))->toBe($binary)
        ->and(File::get($restore->target.'/.env'))->toBe('EXAMPLE=fixture-only')
        ->and(is_dir($restore->target.'/empty'))->toBeTrue()
        ->and(File::get($this->backupTestRoot.'/source/index.html'))->toBe('New live content')
        ->and(fileperms($restore->target.'/index.html') & 0777)->toBe(0600);
    $again = app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1');
    expect($again->target)->not->toBe($restore->target);
});

it('rejects altered archives before restoring and records the failure', function (): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    File::append($this->backupTestRoot.'/archives/'.$snapshot->location, 'corruption');
    expect(fn () => app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1'))->toThrow(RuntimeException::class, 'integrity');
    expect(BackupRestore::query()->first()->status)->toBe(RestoreStatus::Failed)
        ->and(File::directories($this->backupTestRoot.'/restores'))->toBe([]);
});

it('does not restore another teams snapshot', function (): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    expect(fn () => app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-2'))->toThrow(RuntimeException::class);
    expect(BackupRestore::query()->count())->toBe(0);
});

it('requires the original authentication key even if the checksum is replaced', function (): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    config(['hosting-backups.keys.v1' => Str::random(64)]);
    expect(fn () => app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1'))->toThrow(RuntimeException::class, 'authentication');
});

it('keeps older snapshots restorable after rotating the active key', function (): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    config(['hosting-backups.active_key' => 'v2', 'hosting-backups.keys.v2' => Str::random(64)]);
    $new = app(LocalFileBackups::class)->backup('fixture');
    expect($new->metadata['key_id'])->toBe('v2')
        ->and(app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1')->status)->toBe(RestoreStatus::Completed);
});

it('refuses sources outside the operator allowlist', function (): void {
    expect(fn () => app(LocalFileBackups::class)->backup('/etc'))->toThrow(RuntimeException::class);
    expect(BackupSnapshot::query()->count())->toBe(0);
});

it('requires an active encrypted policy owned by the configured team', function (array $changes): void {
    $this->policy->forceFill($changes)->save();
    expect(fn () => app(LocalFileBackups::class)->backup('fixture'))->toThrow(RuntimeException::class);
    expect(BackupSnapshot::query()->count())->toBe(0);
})->with([
    [['active' => false]], [['encrypted' => false]], [['team_id' => 'other-team']], [['storage_driver' => 's3']],
]);

it('rejects source symlinks and hard links instead of reading their targets', function (bool $hard): void {
    $outside = $this->backupTestRoot.'/outside.txt';
    File::put($outside, 'Do not back up this data');
    $link = $this->backupTestRoot.'/source/link';
    $hard ? link($outside, $link) : symlink($outside, $link);
    expect(fn () => app(LocalFileBackups::class)->backup('fixture'))->toThrow(RuntimeException::class);
    expect(BackupSnapshot::query()->first()->status)->toBe(SnapshotStatus::Failed)
        ->and(File::allFiles($this->backupTestRoot.'/archives'))->toHaveCount(0)
        ->and(File::get($outside))->toBe('Do not back up this data');
})->with([false, true]);

it('enforces archive size and entry limits', function (string $limit): void {
    config(['hosting-backups.'.$limit => 1]);
    File::put($this->backupTestRoot.'/source/second.txt', 'second file');
    expect(fn () => app(LocalFileBackups::class)->backup('fixture'))->toThrow(RuntimeException::class);
    expect(BackupSnapshot::query()->first()->status)->toBe(SnapshotStatus::Failed);
})->with(['max_bytes', 'max_files', 'max_manifest_bytes']);

it('requires private non-overlapping storage directories and a configured key', function (string $case): void {
    match ($case) {
        'public' => chmod($this->backupTestRoot.'/archives', 0755),
        'overlap' => config(['hosting-backups.archive_root' => $this->backupTestRoot.'/source']),
        'key' => config(['hosting-backups.keys' => []]),
        'root' => config(['hosting-backups.archive_root' => '/']),
    };
    expect(fn () => app(LocalFileBackups::class)->backup('fixture'))->toThrow(RuntimeException::class);
    expect(BackupSnapshot::query()->count())->toBe(0);
})->with(['public', 'overlap', 'key', 'root']);

it('rejects traversal and absolute paths even inside an authenticated archive', function (string $path): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    $archive = $this->backupTestRoot.'/archives/'.$snapshot->location;
    $zip = new ZipArchive();
    $zip->open($archive, ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode(['version' => 1, 'entries' => [$path => ['type' => 'file', 'size' => 1, 'sha256' => hash('sha256', 'x')]]], JSON_THROW_ON_ERROR));
    $zip->addFromString('files/'.$path, 'x');
    $password = bin2hex(hash_hkdf('sha256', $this->backupKey, 32, 'hosting-backups:encryption:v1'));
    $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $password);
    $zip->setEncryptionName('files/'.$path, ZipArchive::EM_AES_256, $password);
    $zip->close();
    $context = json_encode(['team' => 'team-1', 'snapshot' => (string) $snapshot->getKey()], JSON_THROW_ON_ERROR);
    $snapshot->forceFill(['checksum' => hash_file('sha256', $archive), 'metadata' => [...$snapshot->metadata, 'signature' => app(EncryptedFileArchive::class)->signature($archive, $this->backupKey, $context)]])->save();
    expect(fn () => app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1'))->toThrow(RuntimeException::class, 'Unsafe archive path');
    expect(File::directories($this->backupTestRoot.'/restores'))->toBe([]);
})->with(['../escape', '/absolute', 'safe/../../escape', 'C:\\escape', 'safe//file']);

it('executes the operator backup and restore commands', function (): void {
    $this->artisan('hosting:backup-files', ['source' => 'fixture'])->assertSuccessful();
    $snapshot = BackupSnapshot::query()->first();
    $this->artisan('hosting:restore-files', ['snapshot' => $snapshot->getKey(), '--team' => 'team-1'])->assertSuccessful();
    $this->artisan('hosting:restore-files', ['snapshot' => $snapshot->getKey()])->assertFailed();
    $this->artisan('hosting:backup-files', ['source' => 'missing'])->assertFailed();
});

it('authenticates the owning team as well as the archive bytes', function (): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    $snapshot->forceFill(['team_id' => 'team-2'])->save();
    expect(fn () => app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-2'))->toThrow(RuntimeException::class, 'authentication');
});

it('backs up and restores an empty directory tree', function (): void {
    File::delete($this->backupTestRoot.'/source/index.html');
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    $restore = app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1');
    expect($restore->status)->toBe(RestoreStatus::Completed)->and(File::allFiles($restore->target))->toBe([]);
});

it('verifies per-file checksums even when the whole archive is authenticated', function (): void {
    $snapshot = app(LocalFileBackups::class)->backup('fixture');
    $archive = $this->backupTestRoot.'/archives/'.$snapshot->location;
    $password = bin2hex(hash_hkdf('sha256', $this->backupKey, 32, 'hosting-backups:encryption:v1'));
    $zip = new ZipArchive();
    $zip->open($archive);
    $zip->setPassword($password);
    $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest['entries']['index.html']['sha256'] = str_repeat('0', 64);
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $password);
    $zip->close();
    $context = json_encode(['team' => 'team-1', 'snapshot' => (string) $snapshot->getKey()], JSON_THROW_ON_ERROR);
    $snapshot->forceFill(['checksum' => hash_file('sha256', $archive), 'metadata' => [...$snapshot->metadata, 'signature' => app(EncryptedFileArchive::class)->signature($archive, $this->backupKey, $context)]])->save();
    expect(fn () => app(LocalFileBackups::class)->restore((string) $snapshot->getKey(), 'team-1'))->toThrow(RuntimeException::class, 'checksum');
    expect(File::directories($this->backupTestRoot.'/restores'))->toBe([]);
});
