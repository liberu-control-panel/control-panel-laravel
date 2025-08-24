<?php

namespace App\Filament\App\Resources\DnsSettings\Pages;

use Exception;
use App\Filament\App\Resources\DnsSettings\DnsSettingResource;
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

    protected function getFormData(): array
    {
        return $this->form->getState();
    }

    public function create(bool $another = false): void
    {
        try {
            $data = $this->getFormData();
            $dnsSetting = DnsSetting::create($data);

            $this->dnsSettingService->updateBindDnsRecord($dnsSetting);

            Notification::make()
                ->title('DNS Setting Created')
                ->body('The DNS setting has been created and BIND records have been updated.')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('An error occurred while creating the DNS setting: ' . $e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
