<?php

namespace App\Filament\App\Resources\ResourceResource\Pages;

use App\Filament\App\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\MySqlDatabaseService;
use Filament\Notifications\Notification;

class CreateResource extends CreateRecord
{
    protected static string $resource = ResourceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $databaseService = app(MySqlDatabaseService::class);

        if ($databaseService->createDatabase($data['name'], $data['charset'], $data['collation'])) {
            $data['user_id'] = $user->id;
            return static::getModel()::create($data);
        } else {
            Notification::make()
                ->title('Database Creation Failed')
                ->body('Failed to create the database in MySQL. Please try again.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
