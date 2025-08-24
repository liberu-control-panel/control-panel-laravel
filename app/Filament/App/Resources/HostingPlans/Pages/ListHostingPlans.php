<?php

namespace App\Filament\App\Resources\HostingPlans\Pages;

use Filament\Actions\CreateAction;
use App\Filament\App\Resources\HostingPlans\HostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHostingPlans extends ListRecords
{
    protected static string $resource = HostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
