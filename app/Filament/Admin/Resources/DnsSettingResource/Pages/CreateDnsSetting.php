<?php

namespace App\Filament\Admin\Resources\DnsSettingResource\Pages;

use App\Filament\Admin\Resources\DnsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\DnsSettingService;
use Filament\Notifications\Notification;
use App\Models\DnsSetting;

class CreateDnsSetting extends CreateRecord {
    protected static string $resource = DnsSettingResource::class;

    public function __construct(protected DnsSettingService $dnsSettingService)
    {
        // ...
    }

    public function create(array $data): DnsSetting
    {
        try {
            $dnsSetting = DnsSetting::create($data);

            $this->dnsSettingService->updateBindDnsRecord($dnsSetting);

            Notification::make()
                ->title('DNS Setting Created')
                ->body('The DNS setting has been created and BIND records have been updated.')
                ->success()
                ->send();

            return $dnsSetting;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('An error occurred while creating the DNS setting: ' . $e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
