<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KubernetesNodeResource\Pages;
use App\Models\KubernetesNode;
use App\Services\KubernetesNodeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class KubernetesNodeResource extends Resource
{
    protected static ?string $model = KubernetesNode::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-server-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Infrastructure';

    protected static ?string $navigationLabel = 'Kubernetes Nodes';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Node Information')
                    ->schema([
                        Forms\Components\Select::make('server_id')
                            ->relationship('server', 'name')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('uid')
                            ->label('UID')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options(KubernetesNode::getStatuses())
                            ->disabled(),
                        Forms\Components\Toggle::make('schedulable')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('System Information')
                    ->schema([
                        Forms\Components\TextInput::make('kubernetes_version')
                            ->disabled(),
                        Forms\Components\TextInput::make('container_runtime')
                            ->disabled(),
                        Forms\Components\TextInput::make('os_image')
                            ->disabled(),
                        Forms\Components\TextInput::make('kernel_version')
                            ->disabled(),
                        Forms\Components\TextInput::make('architecture')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Resources')
                    ->schema([
                        Forms\Components\KeyValue::make('capacity')
                            ->disabled(),
                        Forms\Components\KeyValue::make('allocatable')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Labels & Taints')
                    ->schema([
                        Forms\Components\KeyValue::make('labels')
                            ->disabled(),
                        Forms\Components\KeyValue::make('taints')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('server.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => KubernetesNode::STATUS_READY,
                        'danger' => KubernetesNode::STATUS_NOT_READY,
                        'warning' => KubernetesNode::STATUS_UNKNOWN,
                        'secondary' => KubernetesNode::STATUS_SCHEDULING_DISABLED,
                    ]),
                Tables\Columns\IconColumn::make('schedulable')
                    ->boolean(),
                Tables\Columns\TextColumn::make('role')
                    ->getStateUsing(fn (KubernetesNode $record) => $record->getRole())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'master' => 'warning',
                        'worker' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('cpu')
                    ->label('CPU')
                    ->getStateUsing(fn (KubernetesNode $record) => 
                        $record->getAllocatableCpu() 
                            ? number_format($record->getAllocatableCpu(), 1) . ' cores'
                            : 'N/A'
                    ),
                Tables\Columns\TextColumn::make('memory')
                    ->label('Memory')
                    ->getStateUsing(fn (KubernetesNode $record) => 
                        $record->getAllocatableMemory() 
                            ? number_format($record->getAllocatableMemory(), 1) . ' GB'
                            : 'N/A'
                    ),
                Tables\Columns\TextColumn::make('kubernetes_version')
                    ->label('K8s Version')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_heartbeat_time')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(KubernetesNode::getStatuses()),
                Tables\Filters\SelectFilter::make('server')
                    ->relationship('server', 'name'),
                Tables\Filters\TernaryFilter::make('schedulable')
                    ->label('Schedulable'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cordon')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (KubernetesNode $record) => $record->schedulable)
                    ->action(function (KubernetesNode $record) {
                        $service = app(KubernetesNodeService::class);
                        if ($service->cordonNode($record)) {
                            Notification::make()
                                ->title('Node cordoned successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to cordon node')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('uncordon')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (KubernetesNode $record) => !$record->schedulable)
                    ->action(function (KubernetesNode $record) {
                        $service = app(KubernetesNodeService::class);
                        if ($service->uncordonNode($record)) {
                            Notification::make()
                                ->title('Node uncordoned successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to uncordon node')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('drain')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will evict all pods from the node. Are you sure?')
                    ->action(function (KubernetesNode $record) {
                        $service = app(KubernetesNodeService::class);
                        if ($service->drainNode($record)) {
                            Notification::make()
                                ->title('Node drained successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to drain node')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $service = app(KubernetesNodeService::class);
                        $servers = \App\Models\Server::kubernetes()->active()->get();
                        
                        $synced = 0;
                        foreach ($servers as $server) {
                            if ($service->syncNodes($server)) {
                                $synced++;
                            }
                        }
                        
                        Notification::make()
                            ->title("Synced nodes from {$synced} server(s)")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKubernetesNodes::route('/'),
            'view' => Pages\ViewKubernetesNode::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::ready()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
