<?php

namespace App\Filament\App\Resources\HostingPlans\Pages;

use App\Filament\App\Resources\HostingPlans\HostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHostingPlan extends CreateRecord
{
    protected static string $resource = HostingPlanResource::class;
}
