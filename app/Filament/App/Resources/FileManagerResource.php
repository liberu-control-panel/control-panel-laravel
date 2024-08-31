<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\FileManagerResource\Pages;
use App\Services\FileManagerService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class FileManagerResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'File Manager';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('File/Directory Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type'),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size'),
                Tables\Columns\TextColumn::make('last_modified')
                    ->label('Last Modified')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-download')
                    ->action(function ($record) {
                        return Storage::download($record['path']);
                    })
                    ->visible(fn ($record) => $record['type'] === 'file'),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->action(function ($record, FileManagerService $fileManager) {
                        $fileManager->deleteFile($record['path']);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->action(function (array $records, FileManagerService $fileManager) {
                        foreach ($records as $record) {
                            $fileManager->deleteFile($record['path']);
                        }
                    })
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiles::route('/'),
            'create' => Pages\UploadFile::route('/upload'),
        ];
    }
}