<?php

namespace App\Filament\Admin\Resources\KubernetesNodeResource\Pages;

use App\Filament\Admin\Resources\KubernetesNodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKubernetesNodes extends ListRecords
{
    protected static string $resource = KubernetesNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
