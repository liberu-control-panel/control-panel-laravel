<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\WordPressApplicationResource\Pages;
use App\Models\WordPressApplication;
use App\Models\Domain;
use App\Models\Database;
use App\Services\WordPressService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class WordPressApplicationResource extends Resource
{
    protected static ?string $model = WordPressApplication::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'WordPress';

    protected static string | \UnitEnum | null $navigationGroup = 'Applications';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('WordPress will use this database'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('WordPress Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('site_title')
                            ->label('Site Title')
                            ->required()
                            ->maxLength(255)
                            ->default('My WordPress Site'),

                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->helperText('Full URL including http:// or https://'),

                        Forms\Components\TextInput::make('install_path')
                            ->label('Installation Path')
                            ->default('/public_html')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Path relative to domain root'),

                        Forms\Components\Select::make('php_version')
                            ->label('PHP Version')
                            ->options([
                                '8.1' => 'PHP 8.1',
                                '8.2' => 'PHP 8.2',
                                '8.3' => 'PHP 8.3',
                                '8.4' => 'PHP 8.4',
                            ])
                            ->default('8.2')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Administrator Account')
                    ->schema([
                        Forms\Components\TextInput::make('admin_username')
                            ->label('Admin Username')
                            ->required()
                            ->maxLength(60)
                            ->default('admin'),

                        Forms\Components\TextInput::make('admin_email')
                            ->label('Admin Email')
                            ->email()
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('admin_password')
                            ->label('Admin Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255)
                            ->revealable()
                            ->helperText('Strong password recommended'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('site_title')
                    ->label('Site Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('WP Version')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('success'),

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
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'installing' => 'Installing',
                        'installed' => 'Installed',
                        'failed' => 'Failed',
                        'updating' => 'Updating',
                    ]),

                Tables\Filters\SelectFilter::make('php_version')
                    ->options([
                        '8.1' => 'PHP 8.1',
                        '8.2' => 'PHP 8.2',
                        '8.3' => 'PHP 8.3',
                        '8.4' => 'PHP 8.4',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('install')
                    ->label('Install')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WordPressApplication $record) => $record->status === 'pending' || $record->status === 'failed')
                    ->action(function (WordPressApplication $record) {
                        $service = app(WordPressService::class);
                        
                        if ($service->installWordPress($record)) {
                            Notification::make()
                                ->title('WordPress installation started')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('WordPress installation failed')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('update')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (WordPressApplication $record) => $record->status === 'installed')
                    ->action(function (WordPressApplication $record) {
                        $service = app(WordPressService::class);
                        
                        if ($service->updateWordPress($record)) {
                            Notification::make()
                                ->title('WordPress update started')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('WordPress update failed')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('viewLogs')
                    ->label('View Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->modalContent(fn (WordPressApplication $record) => view('filament.app.resources.wordpress-logs', [
                        'log' => $record->installation_log
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

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
            'index' => Pages\ListWordPressApplications::route('/'),
            'create' => Pages\CreateWordPressApplication::route('/create'),
            'edit' => Pages\EditWordPressApplication::route('/{record}/edit'),
        ];
    }
}
