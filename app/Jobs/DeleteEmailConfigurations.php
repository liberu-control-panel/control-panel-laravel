<?php

namespace App\Jobs;

use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeleteEmailConfigurations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;

    public function __construct(Email $email)
    {
        $this->email = $email;
    }

    public function handle()
    {
        // Remove Dovecot configuration
        Storage::disk('dovecot_config')->delete($this->email->email . '.conf');

        // Remove Postfix configuration
        Storage::disk('postfix_config')->delete($this->email->email . '.cf');

        // Remove mailbox directory
        Storage::disk('dovecot_data')->deleteDirectory($this->email->email);
    }
}