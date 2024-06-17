<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DnsSettingResource\Pages;
use App\Filament\Admin\Resources\DnsSettingResource\RelationManagers;
use App\Models\DnsSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

use App\Services\DnsSettingService;

class DnsSettingResource extends Resource {
    protected static ?string $model = DnsSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public function __construct(protected DnsSettingService $dnsSettingService)
    {
        // ...
    }

    public static function form(Form $form): Form {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('record_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('value')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ttl')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->visibleIf('record_type', 'MX')
                    ->requiredIf('record_type', 'MX'),
            ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('record_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->numeric()
                    ->visibleIf('record_type', 'MX'),
                Tables\Columns\TextColumn::make('ttl')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array {
        return [
            //
        ];
    }

    public function __construct(protected DnsSettingService $dnsSettingService)
    {
        // ...
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListDnsSettings::route('/'),
            'create' => Pages\CreateDnsSetting::route('/create'),
            'edit' => Pages\EditDnsSetting::route('/{record}/edit'),
        ];
    }
}
