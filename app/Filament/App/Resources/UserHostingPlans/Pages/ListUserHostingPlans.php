<?php

namespace App\Filament\App\Resources\UserHostingPlans\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\UserHostingPlans\UserHostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserHostingPlans extends ListRecords
{
    protected static string $resource = UserHostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
