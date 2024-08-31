<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BackupResource\Pages;
use App\Models\Backup;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;

class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-upload';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ])
                    ->required(),
                Forms\Components\TimePicker::make('time')
                    ->required(),
                Forms\Components\TextInput::make('retention_days')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(365),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\TextColumn::make('time'),
                Tables\Columns\TextColumn::make('retention_days'),
                Tables\Columns\TextColumn::make('last_backup_at'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('run')
                    ->action(fn (Backup $record) => $record->run())
                    ->requiresConfirmation(),
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
            'index' => Pages\ListBackups::route('/'),
            'create' => Pages\CreateBackup::route('/create'),
            'edit' => Pages\EditBackup::route('/{record}/edit'),
        ];
    }
}