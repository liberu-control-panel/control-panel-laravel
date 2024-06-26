<?php

namespace App\Filament\Admin\Resources\HostingPlanResource\Pages;

use App\Filament\Admin\Resources\HostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHostingPlans extends ListRecords
{
    protected static string $resource = HostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
