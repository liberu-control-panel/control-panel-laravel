<?php

namespace App\Filament\App\Resources\LaravelApplicationResource\Pages;

use App\Filament\App\Resources\LaravelApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListLaravelApplications extends ListRecords
{
    protected static string $resource = LaravelApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
