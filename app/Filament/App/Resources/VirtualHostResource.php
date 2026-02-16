<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\VirtualHostResource\Pages;
use App\Models\VirtualHost;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VirtualHostResource extends Resource
{
    protected static ?string $model = VirtualHost::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationLabel = 'Virtual Hosts';

    protected static string | \UnitEnum | null $navigationGroup = 'Hosting';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('hostname')
                            ->label('Hostname')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('e.g., example.com or www.example.com'),

                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name', fn (Builder $query) => 
                                $query->where('user_id', auth()->id())
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('domain_name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Select::make('server_id')
                            ->label('Server')
                            ->relationship('server', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to auto-assign'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('document_root')
                            ->label('Document Root')
                            ->default('/var/www/html')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('php_version')
                            ->label('PHP Version')
                            ->options(VirtualHost::getPhpVersions())
                            ->default('8.3')
                            ->required(),

                        Forms\Components\TextInput::make('port')
                            ->label('Port')
                            ->numeric()
                            ->default(80)
                            ->minValue(1)
                            ->maxValue(65535),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(VirtualHost::getStatuses())
                            ->default(VirtualHost::STATUS_PENDING)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('SSL/TLS Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('ssl_enabled')
                            ->label('Enable SSL/TLS')
                            ->default(false)
                            ->live(),

                        Forms\Components\Toggle::make('letsencrypt_enabled')
                            ->label('Use Let\'s Encrypt')
                            ->default(true)
                            ->visible(fn (Forms\Get $get) => $get('ssl_enabled')),

                        Forms\Components\Select::make('ssl_certificate_id')
                            ->label('SSL Certificate')
                            ->relationship('sslCertificate', 'domain', fn (Builder $query) => 
                                $query->where('user_id', auth()->id())
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('ssl_enabled') && !$get('letsencrypt_enabled')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Advanced')
                    ->schema([
                        Forms\Components\Textarea::make('nginx_config')
                            ->label('Custom NGINX Configuration')
                            ->rows(10)
                            ->columnSpanFull()
                            ->helperText('Advanced users only. Leave empty for auto-generated config.'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('hostname')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('ssl_enabled')
                    ->label('SSL')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('letsencrypt_enabled')
                    ->label('Let\'s Encrypt')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => VirtualHost::STATUS_ACTIVE,
                        'warning' => VirtualHost::STATUS_PENDING,
                        'danger' => VirtualHost::STATUS_ERROR,
                        'secondary' => VirtualHost::STATUS_INACTIVE,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(VirtualHost::getStatuses()),

                Tables\Filters\SelectFilter::make('php_version')
                    ->options(VirtualHost::getPhpVersions()),

                Tables\Filters\Filter::make('ssl_enabled')
                    ->query(fn (Builder $query): Builder => $query->where('ssl_enabled', true))
                    ->label('SSL Enabled'),

                Tables\Filters\Filter::make('letsencrypt_enabled')
                    ->query(fn (Builder $query): Builder => $query->where('letsencrypt_enabled', true))
                    ->label('Let\'s Encrypt Enabled'),
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
            'index' => Pages\ListVirtualHosts::route('/'),
            'create' => Pages\CreateVirtualHost::route('/create'),
            'edit' => Pages\EditVirtualHost::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
