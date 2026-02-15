<?php

namespace App\Filament\App\Resources\WebsiteResource\Pages;

use App\Filament\App\Resources\WebsiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewWebsite extends ViewRecord
{
    protected static string $resource = WebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Website Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Website Name'),
                        Infolists\Components\TextEntry::make('domain')
                            ->label('Domain')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('platform')
                            ->label('Platform')
                            ->badge()
                            ->formatStateUsing(fn ($state) => \App\Models\Website::getPlatforms()[$state] ?? $state),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'error' => 'danger',
                                'inactive' => 'secondary',
                                'maintenance' => 'info',
                                default => 'secondary',
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Configuration')
                    ->schema([
                        Infolists\Components\TextEntry::make('php_version')
                            ->label('PHP Version'),
                        Infolists\Components\TextEntry::make('database_type')
                            ->label('Database Type'),
                        Infolists\Components\TextEntry::make('document_root')
                            ->label('Document Root'),
                        Infolists\Components\TextEntry::make('server.name')
                            ->label('Server'),
                        Infolists\Components\IconEntry::make('ssl_enabled')
                            ->label('SSL Enabled')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('auto_ssl')
                            ->label('Auto SSL')
                            ->boolean(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Performance Metrics')
                    ->schema([
                        Infolists\Components\TextEntry::make('uptime_percentage')
                            ->label('Uptime Percentage')
                            ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                            ->color(fn ($state) => $state >= 99.9 ? 'success' : ($state >= 99.0 ? 'warning' : 'danger')),
                        Infolists\Components\TextEntry::make('average_response_time')
                            ->label('Average Response Time')
                            ->formatStateUsing(fn ($state) => $state . ' ms'),
                        Infolists\Components\TextEntry::make('monthly_visitors')
                            ->label('Monthly Visitors')
                            ->formatStateUsing(fn ($state) => number_format($state)),
                        Infolists\Components\TextEntry::make('disk_usage_mb')
                            ->label('Disk Usage')
                            ->formatStateUsing(fn ($state) => number_format($state, 2) . ' MB'),
                        Infolists\Components\TextEntry::make('monthly_bandwidth')
                            ->label('Monthly Bandwidth')
                            ->formatStateUsing(fn ($state) => number_format($state / 1024 / 1024, 2) . ' MB'),
                        Infolists\Components\TextEntry::make('last_checked_at')
                            ->label('Last Checked')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
