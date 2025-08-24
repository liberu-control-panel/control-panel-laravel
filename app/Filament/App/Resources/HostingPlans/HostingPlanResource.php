<?php

namespace App\Filament\App\Resources\HostingPlans;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\HostingPlans\Pages\ListHostingPlans;
use App\Filament\App\Resources\HostingPlans\Pages\CreateHostingPlan;
use App\Filament\App\Resources\HostingPlans\Pages\EditHostingPlan;
use App\Filament\App\Resources\HostingPlanResource\Pages;
use App\Filament\App\Resources\HostingPlanResource\RelationManagers;
use App\Models\HostingPlan;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HostingPlanResource extends Resource
{
    protected static ?string $model = HostingPlan::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('disk_space')
                    ->required()
                    ->numeric(),
                TextInput::make('bandwidth')
                    ->required()
                    ->numeric(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('disk_space')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bandwidth')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListHostingPlans::route('/'),
            'create' => CreateHostingPlan::route('/create'),
            'edit' => EditHostingPlan::route('/{record}/edit'),
        ];
    }
}
