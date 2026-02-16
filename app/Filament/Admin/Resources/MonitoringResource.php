<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use App\Filament\Admin\Resources\MonitoringResource\Pages\ListMonitoring;
use App\Filament\Admin\Resources\MonitoringResource\Pages\CreateMonitoring;
use App\Filament\Admin\Resources\MonitoringResource\Pages\EditMonitoring;
use App\Filament\Admin\Resources\MonitoringResource\Pages\ViewMonitoring;
use App\Filament\Admin\Resources\MonitoringResource\Pages;
use App\Models\ResourceUsage;
use App\Models\AccessLog;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class MonitoringResource extends Resource
{
    protected static ?string $model = ResourceUsage::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('disk_usage')
                    ->required()
                    ->numeric(),
                TextInput::make('bandwidth_usage')
                    ->required()
                    ->numeric(),
                TextInput::make('cpu_usage')
                    ->required()
                    ->numeric(),
                TextInput::make('memory_usage')
                    ->required()
                    ->numeric(),
                TextInput::make('month')
                    ->required(),
                TextInput::make('year')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('disk_usage'),
                TextColumn::make('bandwidth_usage'),
                TextColumn::make('cpu_usage'),
                TextColumn::make('memory_usage'),
                TextColumn::make('month'),
                TextColumn::make('year'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('high_usage')
                    ->query(fn (Builder $query): Builder => $query->where('disk_usage', '>', 80)
                        ->orWhere('bandwidth_usage', '>', 80)
                        ->orWhere('cpu_usage', '>', 80)
                        ->orWhere('memory_usage', '>', 80)),
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
            'index' => ListMonitoring::route('/'),
            'create' => CreateMonitoring::route('/create'),
            'edit' => EditMonitoring::route('/{record}/edit'),
            'view' => ViewMonitoring::route('/{record}'),
        ];
    }
}