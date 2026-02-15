<?php

namespace App\Filament\App\Resources\HelmReleaseResource\Pages;

use App\Filament\App\Resources\HelmReleaseResource;
use App\Services\HelmChartService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHelmReleases extends ListRecords
{
    protected static string $resource = HelmReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Install Chart')
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            HelmReleaseResource\Widgets\HelmStatsWidget::class,
        ];
    }
}
