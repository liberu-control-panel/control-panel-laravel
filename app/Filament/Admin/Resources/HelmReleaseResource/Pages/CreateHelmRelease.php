<?php

namespace App\Filament\Admin\Resources\HelmReleaseResource\Pages;

use App\Filament\Admin\Resources\HelmReleaseResource;
use App\Services\HelmChartService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateHelmRelease extends CreateRecord
{
    protected static string $resource = HelmReleaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'pending';
        $data['installed_at'] = now();
        return $data;
    }

    protected function afterCreate(): void
    {
        $helmService = app(HelmChartService::class);
        
        $result = $helmService->installChart(
            $this->record->server,
            $this->record->chart_name,
            $this->record->release_name,
            $this->record->namespace,
            $this->record->values ?? []
        );

        if ($result['success']) {
            $this->record->update([
                'status' => 'deployed',
                'chart_version' => $result['version'] ?? null,
            ]);

            Notification::make()
                ->title('Chart installed successfully')
                ->body("Release '{$this->record->release_name}' has been deployed.")
                ->success()
                ->send();
        } else {
            $this->record->update(['status' => 'failed']);

            Notification::make()
                ->title('Installation failed')
                ->body($result['message'])
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
