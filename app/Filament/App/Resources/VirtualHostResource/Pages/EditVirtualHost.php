<?php

namespace App\Filament\App\Resources\VirtualHostResource\Pages;

use App\Filament\App\Resources\VirtualHostResource;
use App\Services\VirtualHostService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditVirtualHost extends EditRecord
{
    protected static string $resource = VirtualHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    $virtualHostService = app(VirtualHostService::class);
                    $virtualHostService->delete($this->record);
                }),
        ];
    }

    protected function afterSave(): void
    {
        $virtualHostService = app(VirtualHostService::class);
        
        $result = $virtualHostService->update(
            $this->record,
            $this->record->toArray()
        );

        if ($result['success']) {
            Notification::make()
                ->title('Virtual host updated successfully')
                ->body('Configuration has been applied.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Update warning')
                ->body($result['message'])
                ->warning()
                ->send();
        }
    }
}
