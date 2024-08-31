<?php

namespace App\Filament\Admin\Resources\DomainResource;

use Symfony\Component\Process\Process;

class ContainerRestarter
{
    public function restart(): void
    {
        // Restart Postfix and Dovecot containers
        $process = new Process(['docker-compose', 'restart', 'postfix', 'dovecot']);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to restart containers: ' . $process->getErrorOutput());
        }
    }
}