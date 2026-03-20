<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BackupResource\Pages\CreateBackup;
use App\Filament\Admin\Resources\BackupResource\Pages\EditBackup;
use App\Filament\Admin\Resources\BackupResource\Pages\ListBackups;
use App\Models\BackupSchedule;
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

class BackupResource extends Resource
{
    protected static ?string $model = BackupSchedule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationLabel = 'Backup Schedules';

    protected static ?string $modelLabel = 'Backup Schedule';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule Details')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('type')
                            ->label('Backup Type')
                            ->options(BackupSchedule::getTypes())
                            ->default(BackupSchedule::TYPE_FULL)
                            ->required(),

                        Forms\Components\Select::make('frequency')
                            ->options(BackupSchedule::getFrequencies())
                            ->default(BackupSchedule::FREQUENCY_DAILY)
                            ->required(),

                        Forms\Components\TimePicker::make('schedule_time')
                            ->label('Time')
                            ->default('02:00')
                            ->required(),

                        Forms\Components\TextInput::make('retention_days')
                            ->label('Retention (days)')
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),

                        Forms\Components\Select::make('destination_id')
                            ->label('Backup Destination')
                            ->relationship('destination', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    { 
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('domain.domain_name')->label('Domain')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('frequency')->sortable(),
                Tables\Columns\TextColumn::make('schedule_time')->label('Time')->sortable(),
                Tables\Columns\TextColumn::make('retention_days')->label('Retention')->suffix(' d')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('last_run_at')->label('Last Run')->dateTime()->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBackups::route('/'),
            'create' => CreateBackup::route('/create'),
            'edit'   => EditBackup::route('/{record}/edit'),
        ];
    }
}