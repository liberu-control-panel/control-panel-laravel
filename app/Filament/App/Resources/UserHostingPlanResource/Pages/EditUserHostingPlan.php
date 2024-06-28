<?php

namespace App\Filament\Admin\Resources\UserHostingPlanResource\Pages;

use App\Filament\Admin\Resources\UserHostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHostingPlan extends EditRecord
{
    protected static string $resource = UserHostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
