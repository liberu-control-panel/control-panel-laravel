<?php

namespace App\Filament\App\Resources\DatabaseResource\Pages;

use App\Filament\App\Resources\DatabaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResources extends ListRecords
{
    protected static string $resource = DatabaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery()
    {
        return parent::getTableQuery()->where('user_id', auth()->id());
    }
}
