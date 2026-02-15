<?php

namespace App\Filament\App\Resources\Databases\Pages;

use App\Filament\App\Resources\Databases\DatabaseResource;
use App\Services\MySqlDatabaseService;
use App\Models\DatabaseUser;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateResource extends CreateRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $databaseService = app(MySqlDatabaseService::class);

        // Prefix database name with username
        $username = $user->username ?? 'user_' . $user->id;
        $dbName = $username . '_' . $data['name'];

        // Create the database
        $result = $databaseService->createDatabase($dbName, $data['charset'], $data['collation']);

        if ($result['success']) {
            $data['user_id'] = $user->id;
            $data['name'] = $dbName;
            $data['is_active'] = $data['is_active'] ?? true;

            $database = static::getModel()::create($data);

            // Auto-create database user with same name as database
            $dbUsername = $dbName;
            $password = Str::random(16);

            $userResult = $databaseService->createUser($dbUsername, $password, 'localhost');

            if ($userResult['success']) {
                // Grant all privileges
                $databaseService->grantPrivileges($dbUsername, $dbName, ['ALL']);

                // Save database user record
                DatabaseUser::create([
                    'database_id' => $database->id,
                    'user_id' => $user->id,
                    'username' => $dbUsername,
                    'host' => 'localhost',
                    'privileges' => ['ALL'],
                ]);

                Notification::make()
                    ->title('Database created successfully')
                    ->body("Database and user '{$dbName}' created. Password: {$password}")
                    ->success()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('Database created but user creation failed')
                    ->body($userResult['message'])
                    ->warning()
                    ->send();
            }

            return $database;
        } else {
            Notification::make()
                ->title('Database Creation Failed')
                ->body($result['message'] ?? 'Failed to create the database. Please try again.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
