<?php

namespace App\Filament\App\Resources\Domains;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\Domains\Pages\ListDomains;
use App\Filament\App\Resources\Domains\Pages\CreateDomain;
use App\Filament\App\Resources\Domains\Pages\EditDomain;
use App\Filament\App\Resources\DomainResource\Pages;
use App\Filament\App\Resources\DomainResource\RelationManagers;
use App\Models\Domain;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('domain_name')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('registration_date')
                    ->required(),
                DatePicker::make('expiration_date')
                    ->required(),
                TextInput::make('virtual_host')
                    ->required()
                    ->maxLength(255),
                TextInput::make('letsencrypt_host')
                    ->required()
                    ->maxLength(255),
                TextInput::make('letsencrypt_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('sftp_username')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sftp_password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                TextInput::make('ssh_username')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ssh_password')
                    ->password()
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('domain_name')
                    ->searchable(),
                TextColumn::make('registration_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('expiration_date')
                    ->date()
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

    public function __construct(protected DomainContainerRestarter $containerRestarter)
    {
        // ...
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomains::route('/'),
            'create' => CreateDomain::route('/create'),
            'edit' => EditDomain::route('/{record}/edit'),
        ];
    }


}
