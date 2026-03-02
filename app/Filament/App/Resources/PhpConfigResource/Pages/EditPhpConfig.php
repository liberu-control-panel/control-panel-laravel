<?php

namespace App\Filament\App\Resources\PhpConfigResource\Pages;

use App\Filament\App\Resources\PhpConfigResource\PhpConfigResource;
use App\Services\PhpConfigService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPhpConfig extends EditRecord
{
    protected static string $resource = PhpConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        app(PhpConfigService::class)->deploy($record->domain, $record);
    }
}
