<?php

namespace App\Filament\Admin\Resources\ResourceResource\Pages;

use App\Filament\Admin\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateResource extends CreateRecord
{
    protected static string $resource = ResourceResource::class;
}
