<?php

namespace App\Filament\App\Resources\HelmReleaseResource\Pages;

use App\Filament\App\Resources\HelmReleaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Services\HelmChartService;

class EditHelmRelease extends EditRecord
{
    protected static string $resource = HelmReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upgrade')
                ->label('Upgrade Now')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $helmService = app(HelmChartService::class);
                    $result = $helmService->upgradeRelease(
                        $this->record->server,
                        $this->record->release_name,
                        $this->record->chart_name,
                        $this->record->namespace,
                        $this->record->values ?? []
                    );

                    if ($result['success']) {
                        $this->record->update([
                            'status' => 'deployed',
                            'updated_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Chart upgraded successfully')
                            ->success()
                            ->send();

                        return redirect($this->getResource()::getUrl('index'));
                    } else {
                        Notification::make()
                            ->title('Upgrade failed')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->label('Uninstall')
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
