<?php

namespace App\Filament\App\Resources\ResourceResource\Pages;

use App\Filament\App\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\MySqlDatabaseService;
use Filament\Notifications\Notification;

class EditResource extends EditRecord
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $databaseService = app(MySqlDatabaseService::class);

        if ($record->name !== $data['name'] || $record->charset !== $data['charset'] || $record->collation !== $data['collation']) {
            if ($databaseService->modifyDatabase($data['name'], $data['charset'], $data['collation'])) {
                return $record->update($data);
            } else {
                Notification::make()
                    ->title('Database Modification Failed')
                    ->body('Failed to modify the database in MySQL. Please try again.')
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        return $record->update($data);
    }
}
