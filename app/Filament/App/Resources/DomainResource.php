<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\DomainResource\Pages;
use App\Filament\App\Resources\DomainResource\RelationManagers;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('domain_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('registration_date')
                    ->required(),
                Forms\Components\DatePicker::make('expiration_date')
                    ->required(),
                Forms\Components\TextInput::make('virtual_host')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('letsencrypt_host')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('letsencrypt_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sftp_username')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sftp_password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ssh_username')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ssh_password')
                    ->password()
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiration_date')
                    ->date()
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

    public function __construct(protected DomainContainerRestarter $containerRestarter)
    {
        // ...
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }


}
