<?php

namespace App\Filament\Admin\Resources\ResourceResource\Pages;

use App\Filament\Admin\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResources extends ListRecords
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
