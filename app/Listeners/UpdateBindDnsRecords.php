<?php

namespace App\Listeners;

use App\Events\DnsSettingSaved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateBindDnsRecords implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  \App\Events\DnsSettingSaved  $event
     * @return void
     */
    public function handle(DnsSettingSaved $event)
    {
        $dnsSetting = $event->dnsSetting;

        try {
            $domain = $dnsSetting->domain->name;
            $recordType = $dnsSetting->record_type;
            $recordName = $dnsSetting->name;
            $recordValue = $dnsSetting->value;
            $ttl = $dnsSetting->ttl;

            $zoneFile = "./bind/records/{$domain}.db";

            if ($recordType === 'A') {
                $recordLine = "{$recordName} {$ttl} IN A {$recordValue}";
            } elseif ($recordType === 'MX') {
                $recordLine = "{$domain}. {$ttl} IN MX 10 {$recordValue}.";
            }

            file_put_contents($zoneFile, $recordLine . "\n", FILE_APPEND);

            exec('docker-compose restart bind9');
        } catch (\Exception $e) {
            Log::error('Failed to update Bind9 DNS records: ' . $e->getMessage());
        }
    }
}