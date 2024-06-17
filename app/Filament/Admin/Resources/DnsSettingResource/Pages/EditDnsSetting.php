<?php

namespace App\Filament\Admin\Resources\DnsSettingResource\Pages;

use App\Filament\Admin\Resources\DnsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Providers\DnsSettingService;
use Filament\Notifications\Notification;

class EditDnsSetting extends EditRecord {
    protected static string $resource = DnsSettingResource::class;

    public function __construct(protected DnsSettingService $dnsSettingService)
    {
        // ...
    }

    protected function getHeaderActions(): array {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $data = $this->form->getState();
        $this->record->update($data);

        $this->dnsSettingService->updateBindDnsRecord($this->record);

        Notification::make()
            ->title('DNS Setting Updated')
            ->body('The DNS setting has been updated and BIND records have been updated.')
            ->success()
            ->send();

        if ($shouldRedirect) {
            $this->redirect($this->getRedirectUrl());
        }
        if ($shouldSendSavedNotification) {
            $this->notify('saved');
        }
    }
}
