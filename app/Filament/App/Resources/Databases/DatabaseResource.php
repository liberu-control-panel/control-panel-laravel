<?php

namespace App\Filament\App\Resources\Databases;

use App\Filament\App\Resources\Databases\Pages\ListResources;
use App\Filament\App\Resources\Databases\Pages\CreateResource;
use App\Filament\App\Resources\Databases\Pages\EditResource;
use App\Models\Database;
use App\Services\MySqlDatabaseService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DatabaseResource extends Resource
{
    protected static ?string $model = Database::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Databases';

    protected static string | \UnitEnum | null $navigationGroup = 'Hosting';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Database Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Database Name')
                            ->required()
                            ->maxLength(64)
                            ->alphaDash()
                            ->helperText('Will be prefixed with your username automatically')
                            ->placeholder('myapp_db'),

                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name', fn (Builder $query) => 
                                $query->where('user_id', auth()->id())
                            )
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('engine')
                            ->label('Database Engine')
                            ->options(Database::getEngines())
                            ->default(Database::ENGINE_MARIADB)
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\Select::make('charset')
                            ->label('Character Set')
                            ->options(fn (Forms\Get $get) => [
                                Database::getDefaultCharset($get('engine') ?? Database::ENGINE_MARIADB) => 
                                    Database::getDefaultCharset($get('engine') ?? Database::ENGINE_MARIADB),
                                'utf8mb4' => 'UTF-8 Unicode (utf8mb4)',
                                'utf8' => 'UTF-8 (utf8)',
                                'latin1' => 'Latin1',
                            ])
                            ->default(fn (Forms\Get $get) => 
                                Database::getDefaultCharset($get('engine') ?? Database::ENGINE_MARIADB)
                            )
                            ->required(),

                        Forms\Components\Select::make('collation')
                            ->label('Collation')
                            ->options(fn (Forms\Get $get) => [
                                Database::getDefaultCollation($get('engine') ?? Database::ENGINE_MARIADB) => 
                                    Database::getDefaultCollation($get('engine') ?? Database::ENGINE_MARIADB),
                                'utf8mb4_unicode_ci' => 'UTF-8 Unicode CI',
                                'utf8mb4_general_ci' => 'UTF-8 General CI',
                                'utf8_general_ci' => 'UTF-8 General CI',
                                'latin1_swedish_ci' => 'Latin1 Swedish CI',
                            ])
                            ->default(fn (Forms\Get $get) => 
                                Database::getDefaultCollation($get('engine') ?? Database::ENGINE_MARIADB)
                            )
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Auto-Provisioning')
                    ->schema([
                        Forms\Components\Placeholder::make('auto_provision_info')
                            ->label('Automatic User Creation')
                            ->content('A database user with the same name as the database will be created automatically with full privileges.'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Database Name')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('engine')
                    ->label('Engine')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('charset')
                    ->label('Charset')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : 'N/A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('engine')
                    ->options(Database::getEngines()),

                Tables\Filters\Filter::make('is_active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->label('Active Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Database $record) {
                        $service = app(MySqlDatabaseService::class);
                        $service->dropDatabase($record->name);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            $service = app(MySqlDatabaseService::class);
                            foreach ($records as $record) {
                                $service->dropDatabase($record->name);
                            }
                        }),
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
            'index' => ListResources::route('/'),
            'create' => CreateResource::route('/create'),
            'edit' => EditResource::route('/{record}/edit'),
        ];
    }
}
