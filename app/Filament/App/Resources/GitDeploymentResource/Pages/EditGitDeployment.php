<?php

namespace App\Filament\App\Resources\GitDeploymentResource\Pages;

use App\Filament\App\Resources\GitDeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGitDeployment extends EditRecord
{
    protected static string $resource = GitDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
