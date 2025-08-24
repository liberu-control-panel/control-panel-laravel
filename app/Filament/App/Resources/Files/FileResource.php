<?php

namespace App\Filament\App\Resources\Files;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use App\Filament\App\Resources\Files\Pages\ListFiles;
use App\Filament\App\Resources\Files\Pages\EditFile;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Add form fields if needed
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain_name')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('manage_files')
                    ->label('Manage Files')
                    ->url(fn (Domain $record): string => route('filament.app.resources.files.list', ['record' => $record]))
                    ->icon('heroicon-o-folder-open'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
            'index' => ListFiles::route('/'),
            'edit' => EditFile::route('/{record}/edit'),
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