<?php

namespace App\Filament\App\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\DnsSettingResource\Pages\ListDnsSettings;
use App\Filament\App\Resources\DnsSettingResource\Pages\CreateDnsSetting;
use App\Filament\App\Resources\DnsSettingResource\Pages\EditDnsSetting;
use App\Filament\App\Resources\DnsSettingResource\Pages;
use App\Filament\App\Resources\DnsSettingResource\RelationManagers;
use App\Models\DnsSetting;
use Filament\Forms;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function __construct(protected DnsSettingService $dnsSettingService)
    {
        // ...
    }

    public static function form(Schema $schema): Schema {
        return $schema
            ->components([
                TextInput::make('domain_id')
                    ->required()
                    ->numeric(),
                TextInput::make('record_type')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('value')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ttl')
                    ->required()
                    ->numeric(),
                TextInput::make('priority')
                    ->numeric()
                    ->visibleIf('record_type', 'MX')
                    ->requiredIf('record_type', 'MX'),
            ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('domain_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('record_type')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('value')
                    ->searchable(),
                TextColumn::make('priority')
                    ->numeric()
                    ->visibleIf('record_type', 'MX'),
                TextColumn::make('ttl')
                    ->numeric()
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

    public static function getRelations(): array {
        return [
            //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => ListDnsSettings::route('/'),
            'create' => CreateDnsSetting::route('/create'),
            'edit' => EditDnsSetting::route('/{record}/edit'),
        ];
    }
}
