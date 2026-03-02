<?php

namespace App\Filament\App\Resources\BackupScheduleResource;

use App\Filament\App\Resources\BackupScheduleResource\Pages;
use App\Models\BackupSchedule;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BackupScheduleResource extends Resource
{
    protected static ?string $model = BackupSchedule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Backup Schedules';

    protected static string|\UnitEnum|null $navigationGroup = 'Backups';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Schedule Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name for this backup schedule'),

                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name', fn (Builder $query) =>
                                $query->where('user_id', auth()->id())
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty to back up all domains'),

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
                            ->label('Time of Day')
                            ->default('02:00')
                            ->required(),

                        Forms\Components\TextInput::make('retention_days')
                            ->label('Retention (days)')
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->helperText('Older backups will be pruned automatically'),

                        Forms\Components\Select::make('destination_id')
                            ->label('Backup Destination')
                            ->relationship('destination', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty to use local storage'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Enable Schedule')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->sortable()
                    ->default('All domains'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->sortable(),

                Tables\Columns\TextColumn::make('schedule_time')
                    ->label('Time')
                    ->sortable(),

                Tables\Columns\TextColumn::make('retention_days')
                    ->label('Retention')
                    ->suffix(' days')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBackupSchedules::route('/'),
            'create' => Pages\CreateBackupSchedule::route('/create'),
            'edit'   => Pages\EditBackupSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $q) {
                $q->whereNull('domain_id')
                  ->orWhereHas('domain', fn (Builder $d) => $d->where('user_id', auth()->id()));
            });
    }
}
