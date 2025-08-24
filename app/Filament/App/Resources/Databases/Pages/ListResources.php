<?php

namespace App\Filament\App\Resources\DatabaseResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\DatabaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListResources extends ListRecords
{
    protected static string $resource = DatabaseResource::class;

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
