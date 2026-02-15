<?php

namespace App\Filament\Admin\Resources\HelmReleaseResource\Pages;

use App\Filament\Admin\Resources\HelmReleaseResource;
use App\Filament\Admin\Resources\HelmReleaseResource\Widgets\HelmStatsWidget;
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
