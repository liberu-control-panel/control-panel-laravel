<?php

namespace App\Filament\App\Resources\PhpConfigResource;

use App\Filament\App\Resources\PhpConfigResource\Pages;
use App\Models\Domain;
use App\Models\PhpConfig;
use App\Services\PhpConfigService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PhpConfigResource extends Resource
{
    protected static ?string $model = PhpConfig::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationLabel = 'PHP Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Server Configuration';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Domain & PHP Version')
                    ->schema([
                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name', fn (Builder $query) =>
                                $query->where('user_id', auth()->id())
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(PhpConfig::class, 'domain_id', ignoreRecord: true),

                        Forms\Components\Select::make('php_version')
                            ->label('PHP Version')
                            ->options(array_combine(
                                PhpConfig::getSupportedVersions(),
                                PhpConfig::getSupportedVersions()
                            ))
                            ->default('8.2')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Resource Limits')
                    ->description('These values are written to a per-domain php.ini override file.')
                    ->schema([
                        Forms\Components\TextInput::make('memory_limit')
                            ->label('Memory Limit (MB)')
                            ->numeric()
                            ->default(128)
                            ->minValue(16)
                            ->maxValue(4096)
                            ->suffix('MB')
                            ->required(),

                        Forms\Components\TextInput::make('upload_max_filesize')
                            ->label('Upload Max Filesize (MB)')
                            ->numeric()
                            ->default(64)
                            ->minValue(1)
                            ->maxValue(2048)
                            ->suffix('MB')
                            ->required(),

                        Forms\Components\TextInput::make('post_max_size')
                            ->label('POST Max Size (MB)')
                            ->numeric()
                            ->default(64)
                            ->minValue(1)
                            ->maxValue(2048)
                            ->suffix('MB')
                            ->required(),

                        Forms\Components\TextInput::make('max_execution_time')
                            ->label('Max Execution Time (s)')
                            ->numeric()
                            ->default(60)
                            ->minValue(1)
                            ->maxValue(600)
                            ->suffix('s')
                            ->required(),

                        Forms\Components\TextInput::make('max_input_time')
                            ->label('Max Input Time (s)')
                            ->numeric()
                            ->default(60)
                            ->minValue(1)
                            ->maxValue(600)
                            ->suffix('s')
                            ->required(),

                        Forms\Components\TextInput::make('max_input_vars')
                            ->label('Max Input Variables')
                            ->numeric()
                            ->default(1000)
                            ->minValue(100)
                            ->maxValue(100000)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Error Handling & Miscellaneous')
                    ->schema([
                        Forms\Components\Toggle::make('display_errors')
                            ->label('Display Errors')
                            ->helperText('Disable on production servers.')
                            ->default(false),

                        Forms\Components\Toggle::make('short_open_tag')
                            ->label('Short Open Tag')
                            ->default(false),

                        Forms\Components\TextInput::make('error_reporting')
                            ->label('Error Reporting')
                            ->default('E_ALL & ~E_DEPRECATED & ~E_STRICT')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('session_save_path')
                            ->label('Session Save Path')
                            ->placeholder('/var/lib/php/sessions')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Custom php.ini Directives')
                    ->description('Advanced: add additional key=value pairs that will be appended to the ini file.')
                    ->schema([
                        Forms\Components\KeyValue::make('custom_settings')
                            ->label('Custom Settings')
                            ->keyLabel('Directive')
                            ->valueLabel('Value')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
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

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP Version')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('memory_limit')
                    ->label('Memory')
                    ->suffix(' MB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('upload_max_filesize')
                    ->label('Upload Limit')
                    ->suffix(' MB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_execution_time')
                    ->label('Exec Time')
                    ->suffix('s')
                    ->sortable(),

                Tables\Columns\IconColumn::make('display_errors')
                    ->label('Display Errors')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make()
                    ->after(function (PhpConfig $record) {
                        app(PhpConfigService::class)->deploy($record->domain, $record);
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPhpConfigs::route('/'),
            'create' => Pages\CreatePhpConfig::route('/create'),
            'edit'   => Pages\EditPhpConfig::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('domain', fn (Builder $q) => $q->where('user_id', auth()->id()));
    }
}
