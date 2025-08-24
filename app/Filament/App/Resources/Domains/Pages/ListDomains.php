<?php

namespace App\Filament\App\Resources\Domains\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\Domains\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use App\Models\Domain;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
