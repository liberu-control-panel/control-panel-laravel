<?php

namespace App\Jobs;

use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Filament\App\Resources\EmailResource\DovecotConfigGenerator;
use App\Filament\App\Resources\EmailResource\PostfixConfigGenerator;

class GenerateEmailConfigurations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;

    public function __construct(Email $email)
    {
        $this->email = $email;
    }

    public function handle()
    {
        // Generate Dovecot configuration
        $dovecotConfig = (new DovecotConfigGenerator)->generate($this->email->email, $this->email->password);
        Storage::disk('dovecot_config')->put($this->email->email . '.conf', $dovecotConfig);

        // Generate Postfix configuration
        $postfixConfig = (new PostfixConfigGenerator)->generate($this->email->email, $this->email->password);
        Storage::disk('postfix_config')->put($this->email->email . '.cf', $postfixConfig);

        // Create mailbox directory
        Storage::disk('dovecot_data')->makeDirectory($this->email->email);
    }
}