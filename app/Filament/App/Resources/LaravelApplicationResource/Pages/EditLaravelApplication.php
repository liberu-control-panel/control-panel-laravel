<?php

namespace App\Filament\App\Resources\LaravelApplicationResource\Pages;

use App\Filament\App\Resources\LaravelApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLaravelApplication extends EditRecord
{
    protected static string $resource = LaravelApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
