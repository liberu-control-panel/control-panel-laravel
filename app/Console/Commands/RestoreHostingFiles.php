<?php

namespace App\Console\Commands;

use App\Hosting\Backups\LocalFileBackups;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Liberu\ControlPanel\Backups\BackupsServiceProvider;
use Throwable;

#[Signature('hosting:restore-files {snapshot : Verified snapshot UUID} {--team= : Owning team ID}')]
#[Description('Verify and restore file backup bytes into a new private directory')]
class RestoreHostingFiles extends Command
{
    public function handle(LocalFileBackups $backups): int
    {
        if (! app()->providerIsLoaded(BackupsServiceProvider::class) || blank($this->option('team'))) {
            $this->error('Enable the backup module and supply the owning --team ID.');

            return self::FAILURE;
        }
        try {
            $restore = $backups->restore((string) $this->argument('snapshot'), (string) $this->option('team'));
            $this->info('File restore completed: '.$restore->getKey());
            $this->line('Private restore directory: '.$restore->getAttribute('target'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Restore failed. Review the operator log; existing websites were not overwritten.');

            return self::FAILURE;
        }
    }
}
