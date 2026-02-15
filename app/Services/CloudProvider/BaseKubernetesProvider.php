<?php

namespace App\Services\CloudProvider;

use App\Models\Domain;
use App\Models\Server;
use App\Services\KubernetesService;
use App\Services\SshConnectionService;
use Illuminate\Support\Facades\Log;
use Exception;

abstract class BaseKubernetesProvider implements CloudProviderInterface
{
    protected KubernetesService $kubernetesService;
    protected SshConnectionService $sshService;

    public function __construct(
        KubernetesService $kubernetesService,
        SshConnectionService $sshService
    ) {
        $this->kubernetesService = $kubernetesService;
        $this->sshService = $sshService;
    }

    /**
     * Enable horizontal pod autoscaling for a domain
     */
    public function enableHorizontalScaling(
        Domain $domain,
        int $minReplicas = 1,
        int $maxReplicas = 10,
        int $targetCpuUtilization = 80
    ): bool {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server || !$server->isKubernetes()) {
                throw new Exception("Server must be Kubernetes type");
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $deploymentName = $this->sanitizeName($domain->domain_name);

            $hpaManifest = [
                'apiVersion' => 'autoscaling/v2',
                'kind' => 'HorizontalPodAutoscaler',
                'metadata' => [
                    'name' => $deploymentName . '-hpa',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'scaleTargetRef' => [
                        'apiVersion' => 'apps/v1',
                        'kind' => 'Deployment',
                        'name' => $deploymentName,
                    ],
                    'minReplicas' => $minReplicas,
                    'maxReplicas' => $maxReplicas,
                    'metrics' => [
                        [
                            'type' => 'Resource',
                            'resource' => [
                                'name' => 'cpu',
                                'target' => [
                                    'type' => 'Utilization',
                                    'averageUtilization' => $targetCpuUtilization,
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $hpaManifest, 'hpa');

            Log::info("Enabled horizontal scaling for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to enable horizontal scaling: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable vertical pod autoscaling for a domain
     */
    public function enableVerticalScaling(
        Domain $domain,
        array $options = []
    ): bool {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server || !$server->isKubernetes()) {
                throw new Exception("Server must be Kubernetes type");
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $deploymentName = $this->sanitizeName($domain->domain_name);

            $updateMode = $options['update_mode'] ?? 'Auto';

            $vpaManifest = [
                'apiVersion' => 'autoscaling.k8s.io/v1',
                'kind' => 'VerticalPodAutoscaler',
                'metadata' => [
                    'name' => $deploymentName . '-vpa',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'targetRef' => [
                        'apiVersion' => 'apps/v1',
                        'kind' => 'Deployment',
                        'name' => $deploymentName,
                    ],
                    'updatePolicy' => [
                        'updateMode' => $updateMode, // Auto, Recreate, Initial, or Off
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $vpaManifest, 'vpa');

            Log::info("Enabled vertical scaling for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to enable vertical scaling: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable horizontal scaling for a domain
     */
    public function disableHorizontalScaling(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $hpaName = $this->sanitizeName($domain->domain_name) . '-hpa';

            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} delete hpa {$hpaName} -n {$namespace} --ignore-not-found=true";

            $result = $this->sshService->execute($server, $command);

            Log::info("Disabled horizontal scaling for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to disable horizontal scaling: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable vertical scaling for a domain
     */
    public function disableVerticalScaling(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $vpaName = $this->sanitizeName($domain->domain_name) . '-vpa';

            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} delete vpa {$vpaName} -n {$namespace} --ignore-not-found=true";

            $result = $this->sshService->execute($server, $command);

            Log::info("Disabled vertical scaling for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to disable vertical scaling: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current scaling configuration for a domain
     */
    public function getScalingConfig(Domain $domain): array
    {
        $config = [
            'horizontal' => null,
            'vertical' => null,
        ];

        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return $config;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');

            // Get HPA config
            $hpaName = $this->sanitizeName($domain->domain_name) . '-hpa';
            $command = "{$kubectlPath} get hpa {$hpaName} -n {$namespace} -o json 2>/dev/null";
            $result = $this->sshService->execute($server, $command);

            if ($result['success'] && !empty($result['output'])) {
                $hpaData = json_decode($result['output'], true);
                if ($hpaData) {
                    $config['horizontal'] = [
                        'min_replicas' => $hpaData['spec']['minReplicas'] ?? 1,
                        'max_replicas' => $hpaData['spec']['maxReplicas'] ?? 10,
                        'current_replicas' => $hpaData['status']['currentReplicas'] ?? 0,
                        'desired_replicas' => $hpaData['status']['desiredReplicas'] ?? 0,
                    ];
                }
            }

            // Get VPA config
            $vpaName = $this->sanitizeName($domain->domain_name) . '-vpa';
            $command = "{$kubectlPath} get vpa {$vpaName} -n {$namespace} -o json 2>/dev/null";
            $result = $this->sshService->execute($server, $command);

            if ($result['success'] && !empty($result['output'])) {
                $vpaData = json_decode($result['output'], true);
                if ($vpaData) {
                    $config['vertical'] = [
                        'update_mode' => $vpaData['spec']['updatePolicy']['updateMode'] ?? 'Off',
                        'recommendations' => $vpaData['status']['recommendation'] ?? null,
                    ];
                }
            }

        } catch (Exception $e) {
            Log::error("Failed to get scaling config: " . $e->getMessage());
        }

        return $config;
    }

    /**
     * Get current replica count for a domain
     */
    public function getCurrentReplicas(Domain $domain): int
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return 0;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $deploymentName = $this->sanitizeName($domain->domain_name);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');

            $command = "{$kubectlPath} get deployment {$deploymentName} -n {$namespace} -o json 2>/dev/null";
            $result = $this->sshService->execute($server, $command);

            if ($result['success'] && !empty($result['output'])) {
                $data = json_decode($result['output'], true);
                return $data['status']['replicas'] ?? 0;
            }

        } catch (Exception $e) {
            Log::error("Failed to get current replicas: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Manually scale a domain to a specific number of replicas
     */
    public function scaleToReplicas(Domain $domain, int $replicas): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $deploymentName = $this->sanitizeName($domain->domain_name);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');

            $command = "{$kubectlPath} scale deployment {$deploymentName} -n {$namespace} --replicas={$replicas}";
            $result = $this->sshService->execute($server, $command);

            if ($result['success']) {
                Log::info("Scaled {$domain->domain_name} to {$replicas} replicas");
                return true;
            }

        } catch (Exception $e) {
            Log::error("Failed to scale to replicas: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get resource metrics for a domain
     */
    public function getResourceMetrics(Domain $domain): array
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return [];
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');

            // Get pod metrics
            $command = "{$kubectlPath} top pods -n {$namespace} --no-headers 2>/dev/null";
            $result = $this->sshService->execute($server, $command);

            if ($result['success'] && !empty($result['output'])) {
                $lines = explode("\n", trim($result['output']));
                $metrics = [];

                foreach ($lines as $line) {
                    if (empty($line)) {
                        continue;
                    }

                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 3) {
                        $metrics[] = [
                            'pod' => $parts[0],
                            'cpu' => $parts[1],
                            'memory' => $parts[2],
                        ];
                    }
                }

                return $metrics;
            }

        } catch (Exception $e) {
            Log::error("Failed to get resource metrics: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Check if horizontal scaling is supported
     */
    public function supportsHorizontalScaling(): bool
    {
        return true;
    }

    /**
     * Check if vertical scaling is supported
     */
    public function supportsVerticalScaling(): bool
    {
        // VPA requires additional installation
        return false;
    }

    /**
     * Apply manifest to Kubernetes cluster
     */
    protected function applyManifest(Server $server, string $namespace, array $manifest, string $name): void
    {
        $yaml = yaml_emit($manifest);
        $tempFile = "/tmp/k8s-{$name}-" . uniqid() . ".yaml";
        $localTempFile = storage_path("app/temp/{$name}-" . uniqid() . ".yaml");

        if (!is_dir(dirname($localTempFile))) {
            mkdir(dirname($localTempFile), 0755, true);
        }

        file_put_contents($localTempFile, $yaml);

        try {
            $this->sshService->uploadFile($server, $localTempFile, $tempFile);

            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} apply -f {$tempFile} -n {$namespace}";

            $result = $this->sshService->execute($server, $command);

            if (!$result['success']) {
                throw new Exception("Failed to apply manifest: " . $result['output']);
            }

            $this->sshService->execute($server, "rm -f {$tempFile}");

        } finally {
            if (file_exists($localTempFile)) {
                unlink($localTempFile);
            }
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
