<?php

namespace App\Console\Commands;

use App\Hosting\Backups\LocalFileBackups;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Liberu\ControlPanel\Backups\BackupsServiceProvider;
use Throwable;

#[Signature('hosting:backup-files {source : Operator-configured source alias}')]
#[Description('Create and verify an encrypted file backup from an allowlisted source')]
class BackupHostingFiles extends Command
{
    public function handle(LocalFileBackups $backups): int
    {
        if (! app()->providerIsLoaded(BackupsServiceProvider::class)) {
            $this->error('Enable the control-panel-backups module before executing backups.');

            return self::FAILURE;
        }
        try {
            $snapshot = $backups->backup((string) $this->argument('source'));
            $this->info('Verified file snapshot: '.$snapshot->getKey());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup command failed. Review the operator log and snapshot status.');

            return self::FAILURE;
        }
    }
}
