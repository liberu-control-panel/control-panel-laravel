<?php

namespace App\Filament\App\Resources\UserHostingPlanResource\Pages;

use App\Filament\App\Resources\UserHostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserHostingPlans extends ListRecords
{
    protected static string $resource = UserHostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
