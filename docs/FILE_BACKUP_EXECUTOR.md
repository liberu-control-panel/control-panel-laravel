# Encrypted local-file backup and restore

The host now supplies a real file executor alongside the backup module's snapshot and restore records. It is opt-in and operator-only: it does not expose arbitrary filesystem paths to panel users, invoke shell commands, or automatically process every queued backup record.

## What it does

- Copies regular files from an operator-configured source alias into a private staging directory; includes hidden files and empty directories.
- Rejects symbolic links, multiply linked files, special files, unsafe relative paths, overlapping source/storage roots, and configured entry/byte limits.
- Creates an AES-256 ZIP with an encrypted manifest and file contents. ZIP directory listings and entry names are **not confidential**. Encryption uses PHP's [ZipArchive entry encryption](https://www.php.net/manual/en/ziparchive.setencryptionname.php).
- Reads the archive back and verifies each file's size and SHA-256 before marking its snapshot verified. The archive HMAC is bound to its team and snapshot ID, with a key derived separately from the encryption password.
- On restore, checks the archive checksum and HMAC, verifies all entries, writes into a newly generated private directory, then hashes the files read back from disk. No existing website or restore directory is replaced.
- Records success/failure in the backup module's existing snapshot/restore tables. Ordinary failures clean up the newly created staging/restore directory. A process kill or cleanup failure can leave private partial directories for operator inspection; do not serve them as websites.

This is a **file-only** backup. It does not create database dumps, snapshot a live application atomically, back up remote nodes, upload offsite, apply retention, restore ownership/ACLs, or activate the restored website. A verified archive is not proof of application consistency or recoverability of a live database. Quiesce the application or point the source at a stable filesystem snapshot before running it.

## Configure deliberately

1. Enable the `control-panel-backups` module through the deployment's existing module configuration.
2. Install PHP's ZIP extension with AES-256 support. Run the executor as a dedicated, least-privileged service account, not root. Give it read access only to approved source trees. Other accounts must not be able to modify the archive/restore roots or their parents.
3. Pre-create separate archive and restore roots with mode `0700`, outside every source tree and outside any web-served directory. Set `HOSTING_BACKUP_ROOT` and `HOSTING_RESTORE_ROOT` to their absolute paths.
4. Generate at least 32 random characters for `HOSTING_BACKUP_KEY` using your secret manager. Keep an independent protected recovery copy. Never commit the value or put it in a command argument. Losing the key makes the archives unreadable.
5. Create an active backup policy for the owning team with `storage_driver=local-files-v1` and `encrypted=true`.
6. Add the source alias to `config/hosting-backups.php` through your deployment configuration. The policy and team IDs must agree. Paths are operator configuration, not team-editable settings:

```php
'sources' => [
    'customer-site' => [
        'team_id' => '123',
        'policy_id' => 'existing-policy-uuid',
        'path' => '/srv/customer-site-snapshot',
    ],
],
```

Configuration changes require the usual Laravel configuration-cache refresh. The default limits are 10,000 entries, 1 GiB of uncompressed file bytes, and a 4 MiB manifest. Reserve disk space for both plaintext staging copies and the encrypted archive. Private permissions do not replace disk encryption or secure handling of temporary data.

## Execute and prove recovery

```bash
php artisan hosting:backup-files customer-site
php artisan hosting:restore-files SNAPSHOT_UUID --team=123
```

The backup command returns a verified snapshot UUID. Restore returns a restore UUID and its generated private directory. Inspect the restored files and validate the application with a disposable database before any deliberate cutover. Restored files use mode `0600` and directories `0700`; ownership, executable modes and deployment permissions must be applied separately by the operator.

The commands return nonzero on failure. Detailed errors go to the operator log; keys are not printed. Missing/invalid configuration is rejected rather than falling back to an unencrypted backup. Keep the application database, archive files, and all required key versions in your disaster-recovery plan.

For key rotation, add a new entry under `keys`, change `active_key`, and retain old keys until every corresponding snapshot is retired. Existing snapshots select their recorded key ID. Rotation does not re-encrypt old archives.

## Verification scope

`tests/Feature/LocalFileBackupsTest.php` uses isolated real filesystem fixtures and ZIP encryption, not mocked file contents. It covers binary/Unicode/hidden files, empty trees, repeated non-overwriting restores, corrupted archives, wrong-team access, metadata team reassignment, key rotation, unsafe links and paths, resource limits, and the CLI path. These tests do not establish remote-host, database, offsite-storage or live-application recovery guarantees.
