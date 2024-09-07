<?php

namespace App\Filament\App\Resources\DomainResource\Pages;

use App\Filament\App\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\DockerComposeService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    public function __construct(protected DockerComposeService $dockerCompose)
    {
        // ...
    }

    protected function getFormSchema(): array
    {
        return [
            ...parent::getFormSchema(),
            Forms\Components\TextInput::make('sftp_username')
                ->required(),
            Forms\Components\TextInput::make('sftp_password')
                ->password()
                ->required(),
            Forms\Components\TextInput::make('ssh_username')
                ->required(),
            Forms\Components\TextInput::make('ssh_password')
                ->password()
                ->required(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
    
        if ($user->hasReachedDockerComposeLimit()) {
            Notification::make()
                ->title('Docker Compose Limit Reached')
                ->body('You have reached the limit of Docker Compose instances for your hosting plan.')
                ->danger()
                ->send();
    
            return $record;
        }
    
        $hostingPlan = $user->currentHostingPlan();
    
        $record->update($data);
    
        $this->dockerCompose->generateComposeFile($data, $hostingPlan);
        $this->dockerCompose->startServices($data['domain_name']);
    
        return $record;
    }
}
