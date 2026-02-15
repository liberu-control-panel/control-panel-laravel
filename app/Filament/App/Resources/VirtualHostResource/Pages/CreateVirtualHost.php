<?php

namespace App\Filament\App\Resources\VirtualHostResource\Pages;

use App\Filament\App\Resources\VirtualHostResource;
use App\Services\VirtualHostService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateVirtualHost extends CreateRecord
{
    protected static string $resource = VirtualHostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $virtualHostService = app(VirtualHostService::class);
        
        $result = $virtualHostService->update(
            $this->record,
            $this->record->toArray()
        );

        if ($result['success']) {
            Notification::make()
                ->title('Virtual host created successfully')
                ->body('Your virtual host is now active and configured.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Configuration warning')
                ->body($result['message'])
                ->warning()
                ->send();
        }
    }
}
