<?php

namespace App\Filament\App\Resources\BackupScheduleResource\Pages;

use App\Filament\App\Resources\BackupScheduleResource\BackupScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBackupSchedule extends EditRecord
{
    protected static string $resource = BackupScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
