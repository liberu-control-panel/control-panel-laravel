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

class DnsSettingResource extends Resource
{
    protected static ?string $model = DnsSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
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

    public static function table(Table $table): Table
    {
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDnsSettings::route('/'),
            'create' => Pages\CreateDnsSetting::route('/create'),
            'edit' => Pages\EditDnsSetting::route('/{record}/edit'),
        ];
    }

    protected function updateBindDnsRecord(DnsSetting $dnsSetting): void
    {
        switch ($dnsSetting->record_type) {
            case 'A':
                $this->generateARecordEntry($dnsSetting);
                break;
            case 'MX':
                $this->generateMxRecordEntry($dnsSetting);
                break;
        }

        $this->restartBindContainer();
    }

    protected function generateARecordEntry(DnsSetting $dnsSetting): void
    {
        $entry = "{$dnsSetting->name} IN A {$dnsSetting->value}";
        $zonePath = "/etc/bind/records/{$dnsSetting->domain->name}.db";

        Storage::disk('bind')->append($zonePath, $entry);
    }

    protected function generateMxRecordEntry(DnsSetting $dnsSetting): void
    {
        $entry = "{$dnsSetting->name} IN MX {$dnsSetting->priority} {$dnsSetting->value}";
        $zonePath = "/etc/bind/records/{$dnsSetting->domain->name}.db";

        Storage::disk('bind')->append($zonePath, $entry);
    }

    protected function restartBindContainer(): void
    {
        $process = new Process(['docker-compose', 'restart', 'bind9']);
        $process->setWorkingDirectory(base_path());
        $process->run();
    }
}
