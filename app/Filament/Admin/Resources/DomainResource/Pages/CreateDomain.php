<?php

namespace App\Filament\Admin\Resources\DomainResource\Pages;

use App\Filament\Admin\Resources\DomainResource;
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

    public function create(array $data): void
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
            ...$data,
            'hosting_plan_id' => $hostingPlan->id,
        ]);

        $this->dockerCompose->generateComposeFile($data, $hostingPlan);
        $this->dockerCompose->startServices($data['domain_name']);

        redirect()->route('filament.resources.domains.edit', $domain);
    }
}
