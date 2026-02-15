<?php

namespace App\Filament\App\Resources\LaravelApplicationResource\Pages;

use App\Filament\App\Resources\LaravelApplicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLaravelApplication extends CreateRecord
{
    protected static string $resource = LaravelApplicationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
