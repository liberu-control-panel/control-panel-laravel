<?php

namespace App\Filament\App\Resources\Domains\Pages;

use App\Filament\App\Resources\DomainResource\Pages\Forms\Components\TextInput;
use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\Domains\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\ContainerManagerService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    public function __construct(protected ContainerManagerService $containerManager)
    {
        // ...
    }

    protected function getFormSchema(): array
    {
        return [
            ...parent::getFormSchema(),
            TextInput::make('sftp_username')
                ->required(),
            TextInput::make('sftp_password')
                ->password()
                ->required(),
            TextInput::make('ssh_username')
                ->required(),
            TextInput::make('ssh_password')
                ->password()
                ->required(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
    
        if ($user->hasReachedDeploymentLimit()) {
            Notification::make()
                ->title('Deployment Limit Reached')
                ->body('You have reached the limit of deployments for your hosting plan.')
                ->danger()
                ->send();
    
            return $record;
        }
    
        $hostingPlan = $user->currentHostingPlan();
    
        $record->update($data);
    
        // Update hosting environment (automatically selects Docker or Kubernetes)
        $this->containerManager->createHostingEnvironment($record, $data);
    
        return $record;
    }
}
