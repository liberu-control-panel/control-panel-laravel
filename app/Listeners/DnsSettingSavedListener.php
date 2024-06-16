<?php

namespace App\Listeners;

use App\Filament\Admin\Resources\DnsSettingResource;
use App\Models\DnsSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DnsSettingSavedListener
{
    /**
     * Create the event listener.
     */
    public function __construct(protected DnsSettingResource $dnsSettingResource)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DnsSetting $dnsSetting): void
    {
        $this->dnsSettingResource->updateBindDnsRecord($dnsSetting);
    }
}