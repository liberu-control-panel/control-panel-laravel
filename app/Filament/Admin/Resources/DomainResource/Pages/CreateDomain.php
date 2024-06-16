<?php

namespace App\Filament\Admin\Resources\DomainResource\Pages;

use App\Filament\Admin\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;

    protected function handleRecordCreation(array $data): Domain
    {
        $domain = static::getModel()::create($data);

        $composeContent = $this->generateDockerComposeContent($data);
        Storage::disk('local')->put('docker-compose-'.$data['domain_name'].'.yml', $composeContent);

        $process = new Process(['docker-compose', '-f', storage_path('app/docker-compose-'.$data['domain_name'].'.yml'), 'up', '-d']);
        $process->run();

        return $domain;
    }
}
