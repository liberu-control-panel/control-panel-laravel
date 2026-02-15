<?php

namespace App\Filament\App\Resources\Domains\Pages;

use App\Filament\App\Resources\DomainResource\Pages\Forms\Components\TextInput;
use App\Filament\App\Resources\Domains\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

use App\Models\Domain;
use App\Services\ContainerManagerService;
use Filament\Notifications\Notification;

class CreateDomain extends CreateRecord
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

    public function create(bool $another = false): void
    {
        $user = auth()->user();

        if ($user->hasReachedDeploymentLimit()) {
            Notification::make()
                ->title('Deployment Limit Reached')
                ->body('You have reached the limit of deployments for your hosting plan.')
                ->danger()
                ->send();

            return;
        }

        $hostingPlan = $user->currentHostingPlan();

        $domain = Domain::create([
            ...$this->form->getState(),
            'hosting_plan_id' => $hostingPlan->id,
        ]);

        // Create hosting environment (automatically selects Docker or Kubernetes)
        $this->containerManager->createHostingEnvironment($domain, $this->form->getState());

        if ($another) {
            redirect()->route('filament.resources.domains.create');
        } else {
            redirect()->route('filament.resources.domains.edit', $domain);
        }
    }
}
