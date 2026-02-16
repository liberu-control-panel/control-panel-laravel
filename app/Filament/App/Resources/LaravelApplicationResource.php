<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LaravelApplicationResource\Pages;
use App\Models\LaravelApplication;
use App\Models\Domain;
use App\Models\Database;
use App\Services\LaravelApplicationService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class LaravelApplicationResource extends Resource
{
    protected static ?string $model = LaravelApplication::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationLabel = 'Laravel Apps';

    protected static string | \UnitEnum | null $navigationGroup = 'Applications';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $service = app(LaravelApplicationService::class);
        $repositories = $service->getAvailableRepositories();
        
        $repositoryOptions = [];
        foreach ($repositories as $repo) {
            $repositoryOptions[$repo['slug']] = $repo['name'] . ' - ' . $repo['description'];
        }

        return $schema
            ->schema([
                Forms\Components\Section::make('Application Type')
                    ->schema([
                        Forms\Components\Select::make('repository_slug')
                            ->label('Application Type')
                            ->options($repositoryOptions)
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($service) {
                                if ($state) {
                                    $repo = $service->getRepositoryBySlug($state);
                                    if ($repo) {
                                        $set('repository_name', $repo['name']);
                                        $set('repository_url', $repo['repository']);
                                    }
                                }
                            })
                            ->helperText('Select the type of Laravel application to install'),
                    ]),

                Forms\Components\Section::make('Domain & Database')
                    ->schema([
                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\Select::make('database_id')
                            ->label('Database')
                            ->relationship('database', 'database_name')
                            ->searchable()
                            ->preload()
                            ->helperText('Optional: Select a database for the application'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('app_url')
                            ->label('Application URL')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->helperText('Full URL including http:// or https://'),

                        Forms\Components\TextInput::make('install_path')
                            ->label('Installation Path')
                            ->default(config('repositories.default_install_path'))
                            ->required()
                            ->maxLength(255)
                            ->helperText('Path relative to domain root'),

                        Forms\Components\Select::make('php_version')
                            ->label('PHP Version')
                            ->options(config('repositories.php_versions'))
                            ->default(config('repositories.default_php_version'))
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Hidden::make('repository_name'),
                Forms\Components\Hidden::make('repository_url'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('repository_name')
                    ->label('Application')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->url(fn (LaravelApplication $record) => $record->app_url, true),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->sortable()
                    ->color('success')
                    ->default('N/A'),

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'installed' => 'success',
                        'installing' => 'warning',
                        'updating' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('installed_at')
                    ->label('Installed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('repository_slug')
                    ->label('Application Type')
                    ->options(function () {
                        $service = app(LaravelApplicationService::class);
                        $repositories = $service->getAvailableRepositories();
                        $options = [];
                        foreach ($repositories as $repo) {
                            $options[$repo['slug']] = $repo['name'];
                        }
                        return $options;
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'installing' => 'Installing',
                        'installed' => 'Installed',
                        'failed' => 'Failed',
                        'updating' => 'Updating',
                    ]),

                Tables\Filters\SelectFilter::make('php_version')
                    ->options(config('repositories.php_versions')),
            ])
            ->actions([
                Tables\Actions\Action::make('install')
                    ->label('Install')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LaravelApplication $record) => $record->status === 'pending' || $record->status === 'failed')
                    ->action(function (LaravelApplication $record) {
                        $service = app(LaravelApplicationService::class);
                        
                        if ($service->installApplication($record)) {
                            Notification::make()
                                ->title('Application installation started')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Application installation failed')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('update')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (LaravelApplication $record) => $record->status === 'installed')
                    ->action(function (LaravelApplication $record) {
                        $service = app(LaravelApplicationService::class);
                        
                        if ($service->updateApplication($record)) {
                            Notification::make()
                                ->title('Application update started')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Application update failed')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('viewLogs')
                    ->label('View Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->modalContent(fn (LaravelApplication $record) => view('filament.app.resources.laravel-logs', [
                        'log' => $record->installation_log
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('viewRepository')
                    ->label('GitHub')
                    ->icon('heroicon-o-code-bracket-square')
                    ->color('gray')
                    ->url(fn (LaravelApplication $record) => $record->github_url, true),

                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListLaravelApplications::route('/'),
            'create' => Pages\CreateLaravelApplication::route('/create'),
            'edit' => Pages\EditLaravelApplication::route('/{record}/edit'),
        ];
    }
}
