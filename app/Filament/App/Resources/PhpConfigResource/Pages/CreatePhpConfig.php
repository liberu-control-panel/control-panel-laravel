<?php

namespace App\Filament\App\Resources\PhpConfigResource\Pages;

use App\Filament\App\Resources\PhpConfigResource\PhpConfigResource;
use App\Services\PhpConfigService;
use Filament\Resources\Pages\CreateRecord;

class CreatePhpConfig extends CreateRecord
{
    protected static string $resource = PhpConfigResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        app(PhpConfigService::class)->deploy($record->domain, $record);
    }
}
