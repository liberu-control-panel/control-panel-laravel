<?php

namespace App\Filament\Admin\Resources\DomainResource\Pages;

use App\Filament\Admin\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

use App\Models\Domain;
use App\Services\DockerComposeService;


class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(array $data, DockerComposeService $dockerCompose): Model
    {
        $user = auth()->user();

        if ($user->hasReachedDockerComposeLimit()) {
            throw new \Exception('You have reached the limit of Docker Compose instances for your hosting plan.');
        }

        $hostingPlan = $user->currentHostingPlan();

        $this->record->update($data);

        $dockerCompose->generateComposeFile($data, $hostingPlan);
        $dockerCompose->startServices($data['domain_name']);

        return $this->record;
    }
}
