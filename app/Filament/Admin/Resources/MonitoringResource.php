<?php

namespace App\Filament\Admin\Resources;

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

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('disk_usage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('bandwidth_usage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('cpu_usage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('memory_usage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('month')
                    ->required(),
                Forms\Components\TextInput::make('year')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable(),
                Tables\Columns\TextColumn::make('disk_usage'),
                Tables\Columns\TextColumn::make('bandwidth_usage'),
                Tables\Columns\TextColumn::make('cpu_usage'),
                Tables\Columns\TextColumn::make('memory_usage'),
                Tables\Columns\TextColumn::make('month'),
                Tables\Columns\TextColumn::make('year'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\Filter::make('high_usage')
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
            'index' => Pages\ListMonitoring::route('/'),
            'create' => Pages\CreateMonitoring::route('/create'),
            'edit' => Pages\EditMonitoring::route('/{record}/edit'),
            'view' => Pages\ViewMonitoring::route('/{record}'),
        ];
    }
}