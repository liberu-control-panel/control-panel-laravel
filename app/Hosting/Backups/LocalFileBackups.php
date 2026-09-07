<?php

namespace App\Hosting\Backups;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Liberu\ControlPanel\Backups\Enums\RestoreStatus;
use Liberu\ControlPanel\Backups\Enums\SnapshotStatus;
use Liberu\ControlPanel\Backups\Models\BackupPolicy;
use Liberu\ControlPanel\Backups\Models\BackupRestore;
use Liberu\ControlPanel\Backups\Models\BackupSnapshot;
use RuntimeException;
use Throwable;

final readonly class LocalFileBackups
{
    public function __construct(private Repository $config, private Filesystem $files, private EncryptedFileArchive $archives) {}

    public function backup(string $sourceAlias): BackupSnapshot
    {
        $sources = $this->config->get('hosting-backups.sources', []);
        $source = $sources[$sourceAlias] ?? null;
        $this->check(is_array($source) && filled($source['team_id'] ?? null) && filled($source['policy_id'] ?? null), 'Unknown or incomplete backup source alias.');
        $policy = BackupPolicy::query()->whereKey($source['policy_id'])->where('team_id', $source['team_id'])->first();
        $this->check($policy !== null && $policy->getAttribute('active') && $policy->getAttribute('encrypted') && $policy->getAttribute('storage_driver') === 'local-files-v1', 'An active, encrypted local-files-v1 policy for this team is required.');
        $sourceRoot = $this->root($source['path'] ?? null);
        $archiveRoot = $this->root($this->config->get('hosting-backups.archive_root'), true);
        $restoreRoot = $this->root($this->config->get('hosting-backups.restore_root'), true);
        foreach ([$archiveRoot, $restoreRoot] as $root) {
            $this->check(! $this->overlaps($sourceRoot, $root), 'Source, archive and restore directories must not overlap.');
        }
        $this->check(! $this->overlaps($archiveRoot, $restoreRoot), 'Archive and restore directories must not overlap.');
        $keyId = (string) $this->config->get('hosting-backups.active_key');
        $key = $this->key($keyId);
        $id = (string) Str::uuid();
        $snapshot = new BackupSnapshot();
        $snapshot->forceFill([
            'id' => $id, 'team_id' => $source['team_id'], 'policy_id' => $policy->getKey(),
            'location' => $id.'.zip', 'status' => SnapshotStatus::Running,
            'metadata' => ['driver' => 'local-files-v1', 'key_id' => $keyId, 'source_alias' => $sourceAlias],
        ])->save();
        $workspace = $archiveRoot.'/.creating-'.$id;
        try {
            $this->check(mkdir($workspace, 0700), 'Cannot create the private backup workspace.');
            $result = $this->archives->create($sourceRoot, $workspace, $key);
            $this->check(chmod($workspace.'/archive.zip', 0600), 'Cannot secure the backup archive permissions.');
            // link() publishes without replacing an existing archive with the same name.
            $this->check(link($workspace.'/archive.zip', $archiveRoot.'/'.$id.'.zip'), 'Cannot publish the backup archive.');
            $snapshot->forceFill([
                'status' => SnapshotStatus::Verified, 'checksum' => $result['checksum'],
                'size_bytes' => $result['size'], 'verified_at' => now(),
                'metadata' => [...$snapshot->getAttribute('metadata'), 'signature' => $this->archives->signature($archiveRoot.'/'.$id.'.zip', $key, $this->context($snapshot)), 'entries' => $result['files']],
            ])->save();
        } catch (Throwable $exception) {
            $snapshot->forceFill(['status' => SnapshotStatus::Failed])->save();
            throw $exception;
        } finally {
            if (is_dir($workspace)) {
                $this->files->deleteDirectory($workspace);
            }
        }

        return $snapshot->refresh();
    }

    public function restore(string $snapshotId, string $teamId): BackupRestore
    {
        $this->check(Str::isUuid($snapshotId), 'A valid snapshot ID is required.');
        $snapshot = BackupSnapshot::query()->whereKey($snapshotId)->where('team_id', $teamId)->first();
        $metadata = $snapshot?->getAttribute('metadata') ?? [];
        $this->check($snapshot !== null && $snapshot->getAttribute('status') === SnapshotStatus::Verified
            && ($metadata['driver'] ?? null) === 'local-files-v1'
            && $snapshot->getAttribute('location') === $snapshotId.'.zip', 'A verified local-files-v1 snapshot in the requested team is required.');
        $archiveRoot = $this->root($this->config->get('hosting-backups.archive_root'), true);
        $restoreRoot = $this->root($this->config->get('hosting-backups.restore_root'), true);
        $this->check(! $this->overlaps($archiveRoot, $restoreRoot), 'Archive and restore directories must not overlap.');
        $archive = $archiveRoot.'/'.$snapshotId.'.zip';
        $this->check(is_file($archive) && ! is_link($archive), 'The snapshot archive is missing or is a link.');
        $key = $this->key((string) ($metadata['key_id'] ?? ''));
        $id = (string) Str::uuid();
        $target = $restoreRoot.'/'.$id;
        $restore = new BackupRestore();
        $restore->forceFill([
            'id' => $id, 'team_id' => $teamId, 'snapshot_id' => $snapshotId, 'target' => $target,
            'status' => RestoreStatus::Running, 'started_at' => now(), 'options' => ['driver' => 'local-files-v1', 'overwrite' => false],
        ])->save();
        $created = false;
        try {
            $checksum = $snapshot->getAttribute('checksum');
            $this->check(is_string($checksum) && hash_equals($checksum, hash_file('sha256', $archive))
                && is_string($metadata['signature'] ?? null)
                && hash_equals($metadata['signature'], $this->archives->signature($archive, $key, $this->context($snapshot))), 'Backup integrity or authentication failed.');
            $this->archives->verify($archive, $key);
            $this->check(mkdir($target, 0700), 'Restore requires a new, private directory.');
            $created = true;
            $this->archives->verify($archive, $key, $target);
            $restore->forceFill(['status' => RestoreStatus::Completed, 'finished_at' => now()])->save();
        } catch (Throwable $exception) {
            if ($created) {
                $this->files->deleteDirectory($target);
            }
            $restore->forceFill(['status' => RestoreStatus::Failed, 'finished_at' => now(), 'error' => 'File restore failed; no existing website was overwritten.'])->save();
            throw $exception;
        }

        return $restore->refresh();
    }

    private function root(mixed $path, bool $private = false): string
    {
        $this->check(is_string($path) && str_starts_with($path, '/') && ! is_link($path), 'Configure an existing absolute directory, not a symbolic link.');
        $root = realpath($path);
        $this->check($root !== false && $root !== '/' && is_dir($root), 'The configured directory is missing or unsafe.');
        if ($private) {
            $this->check((fileperms($root) & 0077) === 0, 'Archive and restore roots must be private (mode 0700).');
        }

        return $root;
    }

    private function overlaps(string $first, string $second): bool
    {
        return $first === $second || str_starts_with($first, $second.'/') || str_starts_with($second, $first.'/');
    }

    private function context(BackupSnapshot $snapshot): string
    {
        return json_encode(['team' => (string) $snapshot->getAttribute('team_id'), 'snapshot' => (string) $snapshot->getKey()], JSON_THROW_ON_ERROR);
    }

    private function key(string $id): string
    {
        $keys = $this->config->get('hosting-backups.keys', []);
        $key = $keys[$id] ?? null;
        $this->check(is_string($key) && strlen($key) >= 32, 'Configure a backup key of at least 32 random characters for this key ID.');

        return $key;
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
