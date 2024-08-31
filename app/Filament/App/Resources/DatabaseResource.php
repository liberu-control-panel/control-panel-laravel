<?php

namespace App\Filament\App\Resources;

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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('charset')
                    ->required()
                    ->options([
                        'utf8mb4' => 'UTF-8 Unicode (utf8mb4)',
                        'latin1' => 'Latin1 (latin1)',
                        // Add more options as needed
                    ])
                    ->default('utf8mb4'),
                Forms\Components\Select::make('collation')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('charset'),
                Tables\Columns\TextColumn::make('collation'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Database $record, MySqlDatabaseService $service) {
                        $service->dropDatabase($record->name);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
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
            'index' => Pages\ListResources::route('/'),
            'create' => Pages\CreateResource::route('/create'),
            'edit' => Pages\EditResource::route('/{record}/edit'),
        ];
    }
}
