<?php

namespace App\Filament\Admin\Resources\DomainResource\Pages;

use App\Filament\Admin\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Domain $record, array $data): Domain
    {
        $user = auth()->user();
        if ($user->hasReachedDockerComposeLimit()) {
            throw new \Exception('You have reached the limit of Docker Compose instances for your hosting plan.');
        }

        $record->update($data);

        $composeContent = $this->generateDockerComposeContent($data);
        Storage::disk('local')->put('docker-compose-'.$data['domain_name'].'.yml', $composeContent);

        $process = new Process(['docker-compose', '-f', storage_path('app/docker-compose-'.$data['domain_name'].'.yml'), 'up', '-d']);
        $process->run();

        return $record;
    }
}
