<?php

namespace App\Filament\App\Resources\BackupScheduleResource\Pages;

use App\Filament\App\Resources\BackupScheduleResource\BackupScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBackupSchedules extends ListRecords
{
    protected static string $resource = BackupScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
