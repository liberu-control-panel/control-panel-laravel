<?php

namespace App\Filament\App\Resources\UserHostingPlans\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\UserHostingPlans\UserHostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHostingPlan extends EditRecord
{
    protected static string $resource = UserHostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
