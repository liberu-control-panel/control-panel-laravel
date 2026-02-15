<?php

namespace App\Console\Commands;

use App\Filament\Admin\Resources\EmailResource\ContainerRestarter;
use Illuminate\Console\Command;

class UpdateEmailServersCommand extends Command
{
    protected $signature = 'email-servers:update';

    protected $description = 'Update Dovecot and Postfix services with new email account configurations';

    public function handle()
    {
        $this->info('Updating Dovecot and Postfix services...');

        $containerRestarter = new ContainerRestarter();
        $containerRestarter->restart();

        $this->info('Dovecot and Postfix services updated successfully.');
    }
}