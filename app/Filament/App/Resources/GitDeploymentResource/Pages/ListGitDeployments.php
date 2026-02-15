<?php

namespace App\Filament\App\Resources\GitDeploymentResource\Pages;

use App\Filament\App\Resources\GitDeploymentResource;
use Filament\Resources\Pages\ListRecords;

class ListGitDeployments extends ListRecords
{
    protected static string $resource = GitDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
