<?php

namespace App\Filament\Admin\Resources\BackupResource\Pages;

use App\Filament\Admin\Resources\BackupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBackup extends EditRecord
{
    protected static string $resource = BackupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
