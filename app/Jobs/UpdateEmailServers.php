<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use App\Filament\App\Resources\EmailResource\ContainerRestarter;

class UpdateEmailServers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Update Dovecot and Postfix Docker instances
        Artisan::call('email-servers:update');

        // Restart containers
        (new ContainerRestarter)->restart();
    }
}