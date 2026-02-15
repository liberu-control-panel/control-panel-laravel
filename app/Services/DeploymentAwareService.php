<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Exception;

class DeploymentAwareService
{
    protected DeploymentDetectionService $detectionService;
    protected KubernetesService $kubernetesService;
    protected DockerComposeService $dockerService;

    public function __construct(
        DeploymentDetectionService $detectionService,
        KubernetesService $kubernetesService,
        DockerComposeService $dockerService
    ) {
        $this->detectionService = $detectionService;
        $this->kubernetesService = $kubernetesService;
        $this->dockerService = $dockerService;
    }

    /**
     * Deploy a domain using the appropriate method
     */
    public function deployDomain(Domain $domain, array $options = []): bool
    {
        $server = $domain->server ?? Server::getDefault();
        
        if (!$server) {
            Log::error("No server available for domain deployment");
            return false;
        }

        // Route to appropriate deployment service based on server type
        return match($server->type) {
            Server::TYPE_KUBERNETES => $this->deployToKubernetes($domain, $server, $options),
            Server::TYPE_DOCKER => $this->deployToDocker($domain, $server, $options),
            Server::TYPE_STANDALONE => $this->deployToStandalone($domain, $server, $options),
            default => throw new Exception("Unknown server type: {$server->type}"),
        };
    }

    /**
     * Deploy to Kubernetes
     */
    protected function deployToKubernetes(Domain $domain, Server $server, array $options): bool
    {
        try {
            Log::info("Deploying {$domain->domain_name} to Kubernetes on {$server->name}");
            return $this->kubernetesService->deployDomain($domain, $options);
        } catch (Exception $e) {
            Log::error("Kubernetes deployment failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deploy to Docker Compose
     */
    protected function deployToDocker(Domain $domain, Server $server, array $options): bool
    {
        try {
            Log::info("Deploying {$domain->domain_name} to Docker on {$server->name}");
            
            // Generate Docker Compose file
            $this->dockerService->generateComposeFile([
                'domain_name' => $domain->domain_name,
                'virtual_host' => $domain->virtual_host,
                'letsencrypt_host' => $domain->letsencrypt_host,
                'letsencrypt_email' => $domain->letsencrypt_email ?? config('mail.from.address'),
                'sftp_username' => $domain->sftp_username,
                'sftp_password' => $domain->sftp_password,
                'ssh_username' => $domain->ssh_username,
                'ssh_password' => $domain->ssh_password,
            ], $domain->hostingPlan);

            // Start services
            $this->dockerService->startServices($domain->domain_name);

            return true;
        } catch (Exception $e) {
            Log::error("Docker deployment failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deploy to Standalone server
     */
    protected function deployToStandalone(Domain $domain, Server $server, array $options): bool
    {
        try {
            Log::info("Deploying {$domain->domain_name} to standalone server {$server->name}");
            
            // For standalone, we would typically:
            // 1. Create virtual host configuration
            // 2. Set up SSL certificates
            // 3. Configure FTP/SSH access
            // 4. Create databases if needed
            
            // This would use traditional Linux services
            // For now, log that it's not fully implemented
            Log::warning("Standalone deployment is basic - using traditional methods");
            
            return true;
        } catch (Exception $e) {
            Log::error("Standalone deployment failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a domain deployment
     */
    public function deleteDomain(Domain $domain): bool
    {
        $server = $domain->server ?? Server::getDefault();
        
        if (!$server) {
            return false;
        }

        return match($server->type) {
            Server::TYPE_KUBERNETES => $this->kubernetesService->deleteDomain($domain),
            Server::TYPE_DOCKER => $this->deleteDockerDeployment($domain),
            Server::TYPE_STANDALONE => $this->deleteStandaloneDeployment($domain),
            default => false,
        };
    }

    /**
     * Delete Docker deployment
     */
    protected function deleteDockerDeployment(Domain $domain): bool
    {
        try {
            // Stop and remove containers
            $composeFile = storage_path('app/docker-compose-' . $domain->domain_name . '.yml');
            
            if (file_exists($composeFile)) {
                exec("docker-compose -f {$composeFile} down -v 2>&1", $output, $returnCode);
                
                if ($returnCode === 0) {
                    unlink($composeFile);
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            Log::error("Failed to delete Docker deployment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete standalone deployment
     */
    protected function deleteStandaloneDeployment(Domain $domain): bool
    {
        try {
            Log::info("Deleting standalone deployment for {$domain->domain_name}");
            // Implement standalone cleanup
            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete standalone deployment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get deployment status
     */
    public function getDeploymentStatus(Domain $domain): array
    {
        $server = $domain->server ?? Server::getDefault();
        
        if (!$server) {
            return ['status' => 'unknown', 'details' => []];
        }

        return match($server->type) {
            Server::TYPE_KUBERNETES => $this->getKubernetesStatus($domain),
            Server::TYPE_DOCKER => $this->getDockerStatus($domain),
            Server::TYPE_STANDALONE => $this->getStandaloneStatus($domain),
            default => ['status' => 'unknown', 'details' => []],
        };
    }

    /**
     * Get Kubernetes deployment status
     */
    protected function getKubernetesStatus(Domain $domain): array
    {
        $pods = $this->kubernetesService->getPodStatus($domain);
        
        $runningPods = collect($pods)->filter(function ($pod) {
            return $pod['status']['phase'] === 'Running';
        })->count();

        return [
            'status' => $runningPods > 0 ? 'running' : 'stopped',
            'details' => [
                'total_pods' => count($pods),
                'running_pods' => $runningPods,
                'pods' => $pods,
            ],
        ];
    }

    /**
     * Get Docker deployment status
     */
    protected function getDockerStatus(Domain $domain): array
    {
        try {
            $containerName = $domain->domain_name;
            exec("docker inspect {$containerName} 2>&1", $output, $returnCode);
            
            if ($returnCode === 0) {
                $info = json_decode(implode("\n", $output), true);
                $state = $info[0]['State'] ?? [];
                
                return [
                    'status' => $state['Running'] ?? false ? 'running' : 'stopped',
                    'details' => $state,
                ];
            }
            
            return ['status' => 'not_found', 'details' => []];
        } catch (Exception $e) {
            return ['status' => 'error', 'details' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Get standalone deployment status
     */
    protected function getStandaloneStatus(Domain $domain): array
    {
        // Check if virtual host exists and is enabled
        return [
            'status' => 'unknown',
            'details' => ['method' => 'standalone'],
        ];
    }

    /**
     * Restart a domain's deployment
     */
    public function restartDomain(Domain $domain): bool
    {
        $server = $domain->server ?? Server::getDefault();
        
        if (!$server) {
            return false;
        }

        return match($server->type) {
            Server::TYPE_KUBERNETES => $this->restartKubernetesDeployment($domain),
            Server::TYPE_DOCKER => $this->restartDockerDeployment($domain),
            Server::TYPE_STANDALONE => $this->restartStandaloneDeployment($domain),
            default => false,
        };
    }

    /**
     * Restart Kubernetes deployment
     */
    protected function restartKubernetesDeployment(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            $namespace = $this->kubernetesService->getNamespace($domain);
            $deploymentName = $this->sanitizeName($domain->domain_name);
            
            $sshService = app(SshConnectionService::class);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            
            $command = "{$kubectlPath} rollout restart deployment/{$deploymentName} -n {$namespace}";
            $result = $sshService->execute($server, $command);
            
            return $result['success'];
        } catch (Exception $e) {
            Log::error("Failed to restart Kubernetes deployment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restart Docker deployment
     */
    protected function restartDockerDeployment(Domain $domain): bool
    {
        try {
            $containerName = $domain->domain_name;
            exec("docker restart {$containerName} 2>&1", $output, $returnCode);
            
            return $returnCode === 0;
        } catch (Exception $e) {
            Log::error("Failed to restart Docker deployment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restart standalone deployment
     */
    protected function restartStandaloneDeployment(Domain $domain): bool
    {
        try {
            // Reload nginx/apache configuration
            exec("sudo systemctl reload nginx 2>&1", $output, $returnCode);
            return $returnCode === 0;
        } catch (Exception $e) {
            Log::error("Failed to restart standalone deployment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sanitize name for Kubernetes
     */
    protected function sanitizeName(string $name): string
    {
        $sanitized = strtolower($name);
        $sanitized = preg_replace('/[^a-z0-9-.]/', '-', $sanitized);
        $sanitized = preg_replace('/-+/', '-', $sanitized);
        $sanitized = trim($sanitized, '-.');
        
        if (strlen($sanitized) > 63) {
            $sanitized = substr($sanitized, 0, 63);
        }
        
        return $sanitized;
    }
}
