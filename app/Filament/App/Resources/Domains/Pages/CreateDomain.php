<?php

namespace App\Filament\App\Resources\Domains\Pages;

use App\Filament\App\Resources\DomainResource\Pages\Forms\Components\TextInput;
use App\Filament\App\Resources\Domains\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

use App\Models\Domain;
use App\Services\DockerComposeService;
use Filament\Notifications\Notification;

class CreateDomain extends CreateRecord
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

        if ($user->hasReachedDockerComposeLimit()) {
            Notification::make()
                ->title('Docker Compose Limit Reached')
                ->body('You have reached the limit of Docker Compose instances for your hosting plan.')
                ->danger()
                ->send();

            return;
        }

        $hostingPlan = $user->currentHostingPlan();

        $domain = Domain::create([
            ...$this->form->getState(),
            'hosting_plan_id' => $hostingPlan->id,
        ]);

        $this->dockerCompose->generateComposeFile($this->form->getState(), $hostingPlan);
        $this->dockerCompose->startServices($this->form->getState()['domain_name']);

        if ($another) {
            redirect()->route('filament.resources.domains.create');
        } else {
            redirect()->route('filament.resources.domains.edit', $domain);
        }
    }
}
