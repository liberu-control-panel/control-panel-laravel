<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\HelmReleaseResource\Pages;
use App\Models\HelmRelease;
use App\Models\Server;
use App\Services\HelmChartService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class HelmReleaseResource extends Resource
{
    protected static ?string $model = HelmRelease::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Helm Charts';

    protected static ?string $navigationGroup = 'Kubernetes';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $helmService = app(HelmChartService::class);
        $charts = $helmService->getAvailableCharts();

        return $form
            ->schema([
                Forms\Components\Section::make('Release Information')
                    ->schema([
                        Forms\Components\Select::make('server_id')
                            ->label('Server')
                            ->relationship('server', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\TextInput::make('release_name')
                            ->label('Release Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Unique name for this installation'),

                        Forms\Components\Select::make('chart_name')
                            ->label('Chart')
                            ->options(collect($charts)->mapWithKeys(function ($chart, $key) {
                                return [$key => $chart['name'] . ' - ' . $chart['description']];
                            })->toArray())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($helmService) {
                                $defaults = $helmService->getDefaultValues($state);
                                $set('values', $defaults);
                            }),

                        Forms\Components\TextInput::make('namespace')
                            ->label('Namespace')
                            ->default('default')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Kubernetes namespace for this release'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('values')
                            ->label('Helm Values')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add value')
                            ->helperText('Custom values to override chart defaults'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('release_name')
                    ->label('Release')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('chart_name')
                    ->label('Chart')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('namespace')
                    ->label('Namespace')
                    ->searchable()
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deployed' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'uninstalled' => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('chart_version')
                    ->label('Version')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('installed_at')
                    ->label('Installed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'deployed' => 'Deployed',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'uninstalled' => 'Uninstalled',
                    ]),

                Tables\Filters\SelectFilter::make('chart_name')
                    ->label('Chart Type')
                    ->options(function () {
                        $helmService = app(HelmChartService::class);
                        $charts = $helmService->getAvailableCharts();
                        return collect($charts)->mapWithKeys(function ($chart, $key) {
                            return [$key => $chart['name']];
                        })->toArray();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync Status')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (HelmRelease $record) {
                        $helmService = app(HelmChartService::class);
                        $status = $helmService->getReleaseStatus(
                            $record->server,
                            $record->release_name,
                            $record->namespace
                        );

                        if ($status) {
                            $record->update([
                                'status' => $status['info']['status'] ?? 'unknown',
                                'chart_version' => $status['chart']['metadata']['version'] ?? null,
                            ]);

                            Notification::make()
                                ->title('Status synced successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to sync status')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('upgrade')
                    ->label('Upgrade')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (HelmRelease $record) {
                        $helmService = app(HelmChartService::class);
                        $result = $helmService->upgradeRelease(
                            $record->server,
                            $record->release_name,
                            $record->chart_name,
                            $record->namespace,
                            $record->values ?? []
                        );

                        if ($result['success']) {
                            $record->update([
                                'status' => 'deployed',
                                'updated_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Chart upgraded successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Upgrade failed')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Uninstall')
                    ->requiresConfirmation()
                    ->action(function (HelmRelease $record) {
                        $helmService = app(HelmChartService::class);
                        $result = $helmService->uninstallRelease(
                            $record->server,
                            $record->release_name,
                            $record->namespace
                        );

                        if ($result['success']) {
                            $record->update(['status' => 'uninstalled']);

                            Notification::make()
                                ->title('Chart uninstalled successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Uninstall failed')
                                ->body($result['message'])
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHelmReleases::route('/'),
            'create' => Pages\CreateHelmRelease::route('/create'),
            'edit' => Pages\EditHelmRelease::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'deployed')->count();
    }
}
