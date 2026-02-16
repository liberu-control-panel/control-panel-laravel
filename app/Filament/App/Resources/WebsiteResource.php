<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\WebsiteResource\Pages;
use App\Filament\App\Resources\WebsiteResource\Widgets\WebsiteStatsWidget;
use App\Models\Website;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Websites';

    protected static string | \UnitEnum | null $navigationGroup = 'Multi-Site Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Website Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name for your website'),

                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('e.g., example.com or www.example.com'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Platform Configuration')
                    ->schema([
                        Forms\Components\Select::make('platform')
                            ->label('Platform')
                            ->options(Website::getPlatforms())
                            ->default(Website::PLATFORM_CUSTOM)
                            ->required(),

                        Forms\Components\Select::make('php_version')
                            ->label('PHP Version')
                            ->options(Website::getPhpVersions())
                            ->default('8.3')
                            ->required()
                            ->visible(fn (Forms\Get $get) => in_array($get('platform'), ['wordpress', 'laravel', 'custom'])),

                        Forms\Components\Select::make('database_type')
                            ->label('Database Type')
                            ->options(Website::getDatabaseTypes())
                            ->default('mysql')
                            ->required(),

                        Forms\Components\TextInput::make('document_root')
                            ->label('Document Root')
                            ->default('/var/www/html')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('server_id')
                            ->label('Server')
                            ->relationship('server', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to auto-assign'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Website::getStatuses())
                            ->default(Website::STATUS_PENDING)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('SSL/TLS Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('ssl_enabled')
                            ->label('Enable SSL/TLS')
                            ->default(false)
                            ->live(),

                        Forms\Components\Toggle::make('auto_ssl')
                            ->label('Auto SSL (Let\'s Encrypt)')
                            ->default(true)
                            ->visible(fn (Forms\Get $get) => $get('ssl_enabled')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Performance Metrics')
                    ->schema([
                        Forms\Components\TextInput::make('uptime_percentage')
                            ->label('Uptime %')
                            ->numeric()
                            ->default(100.00)
                            ->minValue(0)
                            ->maxValue(100)
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('average_response_time')
                            ->label('Avg Response Time (ms)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('monthly_visitors')
                            ->label('Monthly Visitors')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('disk_usage_mb')
                            ->label('Disk Usage (MB)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(4)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-link'),

                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Website::getPlatforms()[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('ssl_enabled')
                    ->label('SSL')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => Website::STATUS_ACTIVE,
                        'warning' => Website::STATUS_PENDING,
                        'danger' => Website::STATUS_ERROR,
                        'secondary' => Website::STATUS_INACTIVE,
                        'info' => Website::STATUS_MAINTENANCE,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('uptime_percentage')
                    ->label('Uptime')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                    ->color(fn ($state) => $state >= 99.9 ? 'success' : ($state >= 99.0 ? 'warning' : 'danger'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('average_response_time')
                    ->label('Avg Response')
                    ->formatStateUsing(fn ($state) => $state . ' ms')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('monthly_visitors')
                    ->label('Visitors')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Website::getStatuses()),

                Tables\Filters\SelectFilter::make('platform')
                    ->options(Website::getPlatforms()),

                Tables\Filters\Filter::make('ssl_enabled')
                    ->query(fn (Builder $query): Builder => $query->where('ssl_enabled', true))
                    ->label('SSL Enabled'),

                Tables\Filters\Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->where('status', Website::STATUS_ACTIVE))
                    ->label('Active Only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListWebsites::route('/'),
            'create' => Pages\CreateWebsite::route('/create'),
            'view' => Pages\ViewWebsite::route('/{record}'),
            'edit' => Pages\EditWebsite::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            WebsiteStatsWidget::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
