<?php

namespace App\Services;

use App\Models\DnsSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DnsSettingService
{
    public function updateBindDnsRecord(DnsSetting $dnsSetting): void
    {
        switch ($dnsSetting->record_type) {
            case 'A':
                $this->generateARecordEntry($dnsSetting);
                break;
            case 'MX':
                $this->generateMxRecordEntry($dnsSetting);
                break;
        }

        $this->restartBindContainer();
    }

    protected function generateARecordEntry(DnsSetting $dnsSetting): void
    {
        $entry = "{$dnsSetting->name} IN A {$dnsSetting->value}";
        $zonePath = "/etc/bind/records/{$dnsSetting->domain->name}.db";

        Storage::disk('bind')->append($zonePath, $entry);
    }

    protected function generateMxRecordEntry(DnsSetting $dnsSetting): void
    {
        $entry = "{$dnsSetting->name} IN MX {$dnsSetting->priority} {$dnsSetting->value}";
        $zonePath = "/etc/bind/records/{$dnsSetting->domain->name}.db";

        Storage::disk('bind')->append($zonePath, $entry);
    }

    protected function restartBindContainer(): void
    {
        $process = new Process(['docker-compose', 'restart', 'bind9']);
        $process->setWorkingDirectory(base_path());
        $process->run();
    }
}