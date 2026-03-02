<?php

namespace App\Filament\App\Resources\PhpConfigResource\Pages;

use App\Filament\App\Resources\PhpConfigResource\PhpConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPhpConfigs extends ListRecords
{
    protected static string $resource = PhpConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
