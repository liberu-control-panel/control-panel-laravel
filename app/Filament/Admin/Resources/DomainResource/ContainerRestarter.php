<?php

namespace App\Filament\Admin\Resources\DomainResource;

class DomainContainerRestarter
{
    public function restart(string $domainName): void
    {
        // Restart necessary containers for the given domain
        $process = new Process(['docker-compose', 'restart', $domainName]);
        $process->setWorkingDirectory(base_path());
        $process->run();
    }
}