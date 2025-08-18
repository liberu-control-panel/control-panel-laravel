<?php

namespace App\Filament\App\Resources\DatabaseResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\DatabaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\MySqlDatabaseService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EditResource extends EditRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
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
