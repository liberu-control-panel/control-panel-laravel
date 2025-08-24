<?php

namespace App\Filament\App\Resources\Databases;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\Databases\Pages\ListResources;
use App\Filament\App\Resources\Databases\Pages\CreateResource;
use App\Filament\App\Resources\Databases\Pages\EditResource;
use App\Filament\App\Resources\DatabaseResource\Pages;
use App\Models\Database;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Services\MySqlDatabaseService;

class DatabaseResource extends Resource
{
    protected static ?string $model = Database::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('charset')
                    ->required()
                    ->options([
                        'utf8mb4' => 'UTF-8 Unicode (utf8mb4)',
                        'latin1' => 'Latin1 (latin1)',
                        // Add more options as needed
                    ])
                    ->default('utf8mb4'),
                Select::make('collation')
                    ->required()
                    ->options([
                        'utf8mb4_unicode_ci' => 'UTF-8 Unicode (utf8mb4_unicode_ci)',
                        'latin1_swedish_ci' => 'Latin1 Swedish (latin1_swedish_ci)',
                        // Add more options as needed
                    ])
                    ->default('utf8mb4_unicode_ci'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('charset'),
                TextColumn::make('collation'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Database $record, MySqlDatabaseService $service) {
                        $service->dropDatabase($record->name);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, MySqlDatabaseService $service) {
                            foreach ($records as $record) {
                                $service->dropDatabase($record->name);
                            }
                        }),
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
            'index' => ListResources::route('/'),
            'create' => CreateResource::route('/create'),
            'edit' => EditResource::route('/{record}/edit'),
        ];
    }
}
