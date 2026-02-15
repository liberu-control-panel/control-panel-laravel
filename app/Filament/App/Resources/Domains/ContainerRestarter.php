<?php

namespace App\Filament\Admin\Resources\DomainResource;

use RuntimeException;
use Symfony\Component\Process\Process;
use App\Models\Server;

class ContainerRestarter
{
    public function restart(): void
    {
        $server = Server::getDefault();
        
        if ($server && $server->isKubernetes()) {
            // Restart Kubernetes pods
            $this->restartKubernetesPods(['postfix', 'dovecot']);
        } else {
            // Restart Docker containers
            $this->restartDockerContainers(['postfix', 'dovecot']);
        }
    }
    
    protected function restartKubernetesPods(array $services): void
    {
        foreach ($services as $service) {
            $process = new Process(['kubectl', 'rollout', 'restart', 'deployment', $service]);
            $process->setWorkingDirectory(base_path());
            $process->run();

            if (!$process->isSuccessful()) {
                throw new RuntimeException("Failed to restart pod {$service}: " . $process->getErrorOutput());
            }
        }
    }
    
    protected function restartDockerContainers(array $services): void
    {
        $process = new Process(['docker-compose', 'restart', ...$services]);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Failed to restart containers: ' . $process->getErrorOutput());
        }
    }
}