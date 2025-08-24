<?php

namespace App\Filament\App\Resources\Files\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\Files\FileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFiles extends ListRecords
{
    protected static string $resource = FileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()->where('user_id', auth()->id());
    }
}