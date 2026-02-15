<?php

namespace App\Filament\Admin\Resources\KubernetesNodeResource\Pages;

use App\Filament\Admin\Resources\KubernetesNodeResource;
use App\Services\KubernetesNodeService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewKubernetesNode extends ViewRecord
{
    protected static string $resource = KubernetesNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $service = app(KubernetesNodeService::class);
                    $service->syncNodes($this->record->server);
                    $this->record->refresh();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Node Information')
                    ->schema([
                        Components\TextEntry::make('name'),
                        Components\TextEntry::make('uid')->label('UID'),
                        Components\TextEntry::make('server.name'),
                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Ready' => 'success',
                                'NotReady' => 'danger',
                                'Unknown' => 'warning',
                                'SchedulingDisabled' => 'secondary',
                                default => 'gray',
                            }),
                        Components\IconEntry::make('schedulable')
                            ->boolean(),
                        Components\TextEntry::make('role')
                            ->getStateUsing(fn ($record) => $record->getRole())
                            ->badge(),
                    ])->columns(3),

                Components\Section::make('System Information')
                    ->schema([
                        Components\TextEntry::make('kubernetes_version'),
                        Components\TextEntry::make('container_runtime'),
                        Components\TextEntry::make('os_image'),
                        Components\TextEntry::make('kernel_version'),
                        Components\TextEntry::make('architecture'),
                    ])->columns(2),

                Components\Section::make('Resources')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('cpu_capacity')
                                    ->label('CPU Capacity')
                                    ->getStateUsing(fn ($record) => 
                                        $record->getCpuCapacity() 
                                            ? number_format($record->getCpuCapacity(), 2) . ' cores'
                                            : 'N/A'
                                    ),
                                Components\TextEntry::make('cpu_allocatable')
                                    ->label('CPU Allocatable')
                                    ->getStateUsing(fn ($record) => 
                                        $record->getAllocatableCpu() 
                                            ? number_format($record->getAllocatableCpu(), 2) . ' cores'
                                            : 'N/A'
                                    ),
                            ]),
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('memory_capacity')
                                    ->label('Memory Capacity')
                                    ->getStateUsing(fn ($record) => 
                                        $record->getMemoryCapacity() 
                                            ? number_format($record->getMemoryCapacity(), 2) . ' GB'
                                            : 'N/A'
                                    ),
                                Components\TextEntry::make('memory_allocatable')
                                    ->label('Memory Allocatable')
                                    ->getStateUsing(fn ($record) => 
                                        $record->getAllocatableMemory() 
                                            ? number_format($record->getAllocatableMemory(), 2) . ' GB'
                                            : 'N/A'
                                    ),
                            ]),
                    ]),

                Components\Section::make('Labels')
                    ->schema([
                        Components\KeyValueEntry::make('labels')
                            ->columnSpanFull(),
                    ]),

                Components\Section::make('Taints')
                    ->schema([
                        Components\KeyValueEntry::make('taints')
                            ->columnSpanFull(),
                    ]),

                Components\Section::make('Addresses')
                    ->schema([
                        Components\KeyValueEntry::make('addresses')
                            ->columnSpanFull(),
                    ]),

                Components\Section::make('Conditions')
                    ->schema([
                        Components\KeyValueEntry::make('conditions')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
