<?php

namespace App\Filament\App\Resources\HostingPlanResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\HostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHostingPlan extends EditRecord
{
    protected static string $resource = HostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
