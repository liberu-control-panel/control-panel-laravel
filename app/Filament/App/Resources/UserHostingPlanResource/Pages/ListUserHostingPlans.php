<?php

namespace App\Filament\Admin\Resources\UserHostingPlanResource\Pages;

use App\Filament\Admin\Resources\UserHostingPlanResource;
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
