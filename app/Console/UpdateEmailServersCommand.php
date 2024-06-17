<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateEmailServersCommand extends Command
{
    protected $signature = 'email-servers:update';

    protected $description = 'Update Dovecot and Postfix Docker instances with new email account configurations';

    public function handle()
    {
        $this->info('Updating Dovecot and Postfix Docker instances...');

        // Restart Dovecot container
        $dovecotRestart = shell_exec('docker restart dovecot');
        $this->info($dovecotRestart);

        // Restart Postfix container  
        $postfixRestart = shell_exec('docker restart postfix');
        $this->info($postfixRestart);

        $this->info('Dovecot and Postfix Docker instances updated successfully.');
    }
}