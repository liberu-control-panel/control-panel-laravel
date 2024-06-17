<?php

namespace App\Filament\Admin\Resources\DomainResource\Pages;

use App\Filament\Admin\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

use App\Models\Domain;
use App\Services\DockerComposeService;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;

    protected DockerComposeService $dockerCompose;

    public function __construct(DockerComposeService $dockerCompose)
    {
        $this->dockerCompose = $dockerCompose;
    }

    protected function handleRecordCreation(array $data): Domain
    {
        $user = auth()->user();

        if ($user->hasReachedDockerComposeLimit()) {
            throw new \Exception('You have reached the limit of Docker Compose instances for your hosting plan.');
        }

        $hostingPlan = $user->currentHostingPlan();

        $this->record->create([
            ...$data,
            'hosting_plan_id' => $hostingPlan->id,
        ]);

        $this->dockerCompose->generateComposeFile($data, $hostingPlan);
        $this->dockerCompose->startServices($data['domain_name']);

        return $this->record;
    }
}
