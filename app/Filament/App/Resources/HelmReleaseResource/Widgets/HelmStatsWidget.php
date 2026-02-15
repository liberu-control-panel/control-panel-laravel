<?php

namespace App\Filament\App\Resources\HelmReleaseResource\Widgets;

use App\Models\HelmRelease;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HelmStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Releases', HelmRelease::count())
                ->description('All Helm releases')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Deployed', HelmRelease::where('status', 'deployed')->count())
                ->description('Successfully deployed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed', HelmRelease::where('status', 'failed')->count())
                ->description('Failed deployments')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Pending', HelmRelease::where('status', 'pending')->count())
                ->description('Awaiting deployment')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
