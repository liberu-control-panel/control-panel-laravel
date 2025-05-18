<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\FileResource\Pages;
use App\Models\Domain;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Services\SftpService;

class FileResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'heroicon-o-document';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                // Add form fields if needed
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('manage_files')
                    ->label('Manage Files')
                    ->url(fn (Domain $record): string => route('filament.app.resources.files.list', ['record' => $record]))
                    ->icon('heroicon-o-folder-open'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
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
            'index' => Pages\ListFiles::route('/'),
            'edit' => Pages\EditFile::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('user', function ($query) {
                $query->where('id', Auth::id());
            });
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canView(Model $record): bool
    {
        return $record->user_id === auth()->id();
    }

    public static function canCreate(): bool
    {
        return auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return $record->user_id === auth()->id();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->user_id === auth()->id();
    }
}