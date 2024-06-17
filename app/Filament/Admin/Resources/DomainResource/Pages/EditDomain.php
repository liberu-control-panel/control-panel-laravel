<?php

namespace App\Filament\Admin\Resources\DomainResource\Pages;

use App\Filament\Admin\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

use App\Models\Domain;
use App\Services\DockerComposeService;


use App\Services\DockerComposeService;
use Filament\Notifications\Notification;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    public function __construct(protected DockerComposeService $dockerCompose)
    {
        // ...
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function save(array $data): Model
    {
        $user = auth()->user();

        if ($user->hasReachedDockerComposeLimit()) {
            Notification::make()
                ->title('Docker Compose Limit Reached')
                ->body('You have reached the limit of Docker Compose instances for your hosting plan.')
                ->danger()
                ->send();

            return $this->record;
        }

        $hostingPlan = $user->currentHostingPlan();

        $this->record->update($data);

        $this->dockerCompose->generateComposeFile($data, $hostingPlan);
        $this->dockerCompose->startServices($data['domain_name']);

        return $this->record;
    }
}
