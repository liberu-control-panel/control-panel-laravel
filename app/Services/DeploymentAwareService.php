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
    protected WebServerService $webServerService;
    protected SslService $sslService;
    protected DatabaseService $databaseService;
    protected StandaloneServiceHelper $standaloneHelper;

    public function __construct(
        DeploymentDetectionService $detectionService,
        KubernetesService $kubernetesService,
        DockerComposeService $dockerService,
        WebServerService $webServerService,
        SslService $sslService,
        DatabaseService $databaseService,
        StandaloneServiceHelper $standaloneHelper
    ) {
        $this->detectionService = $detectionService;
        $this->kubernetesService = $kubernetesService;
        $this->dockerService = $dockerService;
        $this->webServerService = $webServerService;
        $this->sslService = $sslService;
        $this->databaseService = $databaseService;
        $this->standaloneHelper = $standaloneHelper;
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
            
            // 1. Create Nginx virtual host configuration
            $phpVersion = $options['php_version'] ?? '8.2';
            $documentRoot = $options['document_root'] ?? "/var/www/{$domain->domain_name}/public";
            $enableSSL = $options['enable_ssl'] ?? true;
            
            $this->webServerService->createNginxConfig($domain, [
                'php_version' => $phpVersion,
                'document_root' => $documentRoot,
                'enable_ssl' => false, // Initially without SSL
            ]);
            
            // 2. Test Nginx configuration
            $testResult = $this->webServerService->testNginxConfig($domain);
            if (!$testResult['success']) {
                Log::error("Nginx configuration test failed: " . $testResult['error']);
                return false;
            }
            
            // 3. Reload Nginx to apply configuration
            if (!$this->webServerService->reloadNginx($domain)) {
                Log::error("Failed to reload Nginx");
                return false;
            }
            
            // 4. Set up SSL certificates if requested
            if ($enableSSL && $this->standaloneHelper->isCertbotInstalled()) {
                $sslCertificate = $this->sslService->generateLetsEncryptCertificate($domain, [
                    'email' => $domain->user->email ?? config('mail.from.address'),
                    'include_www' => true,
                    'webroot' => $documentRoot
                ]);
                
                if ($sslCertificate) {
                    // Update Nginx config with SSL
                    $this->webServerService->createNginxConfig($domain, [
                        'php_version' => $phpVersion,
                        'document_root' => $documentRoot,
                        'enable_ssl' => true,
                    ]);
                    
                    // Reload Nginx again with SSL config
                    $this->webServerService->reloadNginx($domain);
                }
            }
            
            // 5. Create databases if needed
            if (isset($options['create_database']) && $options['create_database']) {
                $this->databaseService->createDatabase($domain, [
                    'name' => $options['database_name'] ?? $domain->domain_name,
                    'engine' => $options['database_engine'] ?? 'mysql',
                ]);
            }
            
            Log::info("Successfully deployed {$domain->domain_name} to standalone server");
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
            
            // 1. Remove Nginx configuration
            $this->standaloneHelper->removeNginxConfig($domain->domain_name);
            
            // 2. Reload Nginx
            $this->standaloneHelper->reloadSystemdService('nginx');
            
            // 3. Remove SSL certificates if they exist
            // Note: We don't delete Let's Encrypt certificates as they can be reused
            
            Log::info("Successfully deleted standalone deployment for {$domain->domain_name}");
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
        try {
            // Check if Nginx configuration exists
            $configExists = $this->standaloneHelper->nginxConfigExists($domain->domain_name);
            
            // Check if Nginx is running
            $nginxRunning = $this->standaloneHelper->isSystemdServiceRunning('nginx');
            
            // Check if SSL certificate exists
            $sslExists = $this->standaloneHelper->certificateExists($domain->domain_name);
            
            $status = $configExists && $nginxRunning ? 'running' : 'stopped';
            
            return [
                'status' => $status,
                'details' => [
                    'method' => 'standalone',
                    'nginx_config_exists' => $configExists,
                    'nginx_running' => $nginxRunning,
                    'ssl_enabled' => $sslExists,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Failed to get standalone status: " . $e->getMessage());
            return [
                'status' => 'error',
                'details' => ['error' => $e->getMessage()],
            ];
        }
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
            // Test Nginx configuration before restarting
            $testResult = $this->webServerService->testNginxConfig($domain);
            if (!$testResult['success']) {
                Log::error("Nginx configuration test failed, aborting restart: " . $testResult['error']);
                return false;
            }
            
            // Reload Nginx (graceful restart)
            $reloadSuccess = $this->standaloneHelper->reloadSystemdService('nginx');
            
            if ($reloadSuccess) {
                Log::info("Successfully restarted standalone deployment for {$domain->domain_name}");
            } else {
                Log::error("Failed to reload Nginx for {$domain->domain_name}");
            }
            
            return $reloadSuccess;
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
