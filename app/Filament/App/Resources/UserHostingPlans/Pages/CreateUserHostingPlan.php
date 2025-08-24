<?php

namespace App\Filament\App\Resources\UserHostingPlans\Pages;

use App\Filament\App\Resources\UserHostingPlans\UserHostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserHostingPlan extends CreateRecord
{
    protected static string $resource = UserHostingPlanResource::class;
}
