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

    protected function handleRecordCreation(array $data, DockerComposeService $dockerCompose): Domain
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

        $dockerCompose->generateComposeFile($data, $hostingPlan);
        $dockerCompose->startServices($data['domain_name']);

        return $this->record;
    }
}
