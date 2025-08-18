<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use App\Filament\Admin\Resources\BackupResource\Pages\ListBackups;
use App\Filament\Admin\Resources\BackupResource\Pages\CreateBackup;
use App\Filament\Admin\Resources\BackupResource\Pages\EditBackup;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cloud-upload';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ])
                    ->required(),
                TimePicker::make('time')
                    ->required(),
                TextInput::make('retention_days')
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
                TextColumn::make('name'),
                TextColumn::make('frequency'),
                TextColumn::make('time'),
                TextColumn::make('retention_days'),
                TextColumn::make('last_backup_at'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('run')
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
            'index' => ListBackups::route('/'),
            'create' => CreateBackup::route('/create'),
            'edit' => EditBackup::route('/{record}/edit'),
        ];
    }
}