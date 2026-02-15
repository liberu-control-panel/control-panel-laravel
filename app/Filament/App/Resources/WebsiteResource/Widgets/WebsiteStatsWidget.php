<?php

namespace App\Filament\App\Resources\WebsiteResource\Widgets;

use App\Models\Website;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WebsiteStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $userId = auth()->id();
        
        $totalWebsites = Website::where('user_id', $userId)->count();
        $activeWebsites = Website::where('user_id', $userId)
            ->where('status', Website::STATUS_ACTIVE)
            ->count();
        $avgUptime = Website::where('user_id', $userId)
            ->where('status', Website::STATUS_ACTIVE)
            ->avg('uptime_percentage') ?? 0;
        $totalVisitors = Website::where('user_id', $userId)
            ->sum('monthly_visitors');

        return [
            Stat::make('Total Websites', $totalWebsites)
                ->description('All websites in your account')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary'),

            Stat::make('Active Websites', $activeWebsites)
                ->description('Currently running')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Average Uptime', number_format($avgUptime, 2) . '%')
                ->description('Across all active sites')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($avgUptime >= 99.9 ? 'success' : ($avgUptime >= 99.0 ? 'warning' : 'danger')),

            Stat::make('Total Visitors', number_format($totalVisitors))
                ->description('This month')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
