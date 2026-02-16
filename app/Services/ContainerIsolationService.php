<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Domain;
use App\Models\GitDeployment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ContainerIsolationService
{
    protected KubernetesService $kubernetesService;

    public function __construct(KubernetesService $kubernetesService)
    {
        $this->kubernetesService = $kubernetesService;
    }

    /**
     * Create isolated container for a deployment
     */
    public function createIsolatedContainer(GitDeployment $deployment): ?Container
    {
        try {
            $domain = $deployment->domain;
            
            // Create container record
            $container = Container::create([
                'domain_id' => $domain->id,
                'name' => $this->generateContainerName($domain),
                'type' => Container::TYPE_WEB,
                'image' => $this->getWebServerImage(),
                'container_name' => $this->generateContainerName($domain),
                'status' => Container::STATUS_STOPPED,
                'ports' => [
                    ['host' => null, 'container' => 80, 'protocol' => 'tcp'],
                    ['host' => null, 'container' => 443, 'protocol' => 'tcp'],
                ],
                'environment' => [
                    'DOMAIN_NAME' => $domain->domain_name,
                    'DOCUMENT_ROOT' => '/var/www/html' . $deployment->deploy_path,
                ],
                'volumes' => [
                    "/var/www/{$domain->domain_name}:/var/www/html",
                ],
                'cpu_limit' => '1000m',
                'memory_limit' => '512Mi',
                'restart_policy' => 'unless-stopped',
            ]);

            // Update deployment with container reference
            $deployment->update(['container_id' => $container->id]);

            Log::info("Created isolated container for domain {$domain->domain_name}");
            
            return $container;
        } catch (Exception $e) {
            Log::error("Failed to create isolated container: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Kubernetes pod for a deployment
     */
    public function createKubernetesPod(GitDeployment $deployment): bool
    {
        try {
            if (!config('kubernetes.enabled', false)) {
                Log::info("Kubernetes is not enabled");
                return false;
            }

            $domain = $deployment->domain;
            $server = $domain->server;
            
            if (!$server || !$server->isKubernetes()) {
                Log::info("Server is not configured for Kubernetes");
                return false;
            }

            $namespace = $this->getNamespaceForDomain($domain);
            $podName = $this->generatePodName($domain);

            // Create namespace if it doesn't exist
            $this->kubernetesService->createNamespace($server, $namespace);

            // Create pod manifest
            $podManifest = $this->generatePodManifest($deployment, $podName, $namespace);

            // Apply pod to cluster
            $this->kubernetesService->applyManifest($server, $namespace, $podManifest, $podName);

            // Update deployment with Kubernetes details
            $deployment->update([
                'kubernetes_pod_name' => $podName,
                'kubernetes_namespace' => $namespace,
            ]);

            Log::info("Created Kubernetes pod {$podName} in namespace {$namespace}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to create Kubernetes pod: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate pod manifest for deployment
     */
    protected function generatePodManifest(GitDeployment $deployment, string $podName, string $namespace): array
    {
        $domain = $deployment->domain;
        
        return [
            'apiVersion' => 'v1',
            'kind' => 'Pod',
            'metadata' => [
                'name' => $podName,
                'namespace' => $namespace,
                'labels' => [
                    'app' => 'website',
                    'domain' => $this->sanitizeLabelValue($domain->domain_name),
                    'deployment-id' => (string)$deployment->id,
                ],
            ],
            'spec' => [
                'securityContext' => [
                    'runAsNonRoot' => true,
                    'runAsUser' => 1000,
                    'fsGroup' => 1000,
                ],
                'containers' => [
                    [
                        'name' => 'web',
                        'image' => $this->getWebServerImage(),
                        'ports' => [
                            ['containerPort' => 80, 'protocol' => 'TCP'],
                            ['containerPort' => 443, 'protocol' => 'TCP'],
                        ],
                        'env' => [
                            ['name' => 'DOMAIN_NAME', 'value' => $domain->domain_name],
                            ['name' => 'DOCUMENT_ROOT', 'value' => '/var/www/html' . $deployment->deploy_path],
                        ],
                        'volumeMounts' => [
                            [
                                'name' => 'website-content',
                                'mountPath' => '/var/www/html',
                            ],
                        ],
                        'resources' => [
                            'limits' => [
                                'cpu' => '1000m',
                                'memory' => '512Mi',
                            ],
                            'requests' => [
                                'cpu' => '100m',
                                'memory' => '128Mi',
                            ],
                        ],
                    ],
                ],
                'volumes' => [
                    [
                        'name' => 'website-content',
                        'persistentVolumeClaim' => [
                            'claimName' => $this->generatePvcName($domain),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Create service for Kubernetes pod
     */
    public function createKubernetesService(GitDeployment $deployment): bool
    {
        try {
            if (!$deployment->kubernetes_pod_name) {
                return false;
            }

            $domain = $deployment->domain;
            $server = $domain->server;
            
            if (!$server || !$server->isKubernetes()) {
                return false;
            }

            $serviceName = $deployment->kubernetes_pod_name;
            $namespace = $deployment->kubernetes_namespace;

            $serviceManifest = [
                'apiVersion' => 'v1',
                'kind' => 'Service',
                'metadata' => [
                    'name' => $serviceName,
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'selector' => [
                        'app' => 'website',
                        'domain' => $this->sanitizeLabelValue($domain->domain_name),
                    ],
                    'ports' => [
                        [
                            'name' => 'http',
                            'port' => 80,
                            'targetPort' => 80,
                            'protocol' => 'TCP',
                        ],
                        [
                            'name' => 'https',
                            'port' => 443,
                            'targetPort' => 443,
                            'protocol' => 'TCP',
                        ],
                    ],
                    'type' => 'ClusterIP',
                ],
            ];

            $this->kubernetesService->applyManifest($server, $namespace, $serviceManifest, $serviceName);
            return true;
        } catch (Exception $e) {
            Log::error("Failed to create Kubernetes service: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create ingress for Kubernetes pod
     */
    public function createKubernetesIngress(GitDeployment $deployment): bool
    {
        try {
            if (!$deployment->kubernetes_pod_name) {
                return false;
            }

            $domain = $deployment->domain;
            $server = $domain->server;
            
            if (!$server || !$server->isKubernetes()) {
                return false;
            }

            $ingressName = $deployment->kubernetes_pod_name;
            $namespace = $deployment->kubernetes_namespace;
            $serviceName = $deployment->kubernetes_pod_name;

            $ingressManifest = [
                'apiVersion' => 'networking.k8s.io/v1',
                'kind' => 'Ingress',
                'metadata' => [
                    'name' => $ingressName,
                    'namespace' => $namespace,
                    'annotations' => [
                        'kubernetes.io/ingress.class' => config('kubernetes.ingress_class', 'nginx'),
                        'cert-manager.io/cluster-issuer' => config('kubernetes.cert_issuer', 'letsencrypt-prod'),
                    ],
                ],
                'spec' => [
                    'tls' => [
                        [
                            'hosts' => [$domain->domain_name],
                            'secretName' => "{$ingressName}-tls",
                        ],
                    ],
                    'rules' => [
                        [
                            'host' => $domain->domain_name,
                            'http' => [
                                'paths' => [
                                    [
                                        'path' => '/',
                                        'pathType' => 'Prefix',
                                        'backend' => [
                                            'service' => [
                                                'name' => $serviceName,
                                                'port' => [
                                                    'number' => 80,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->kubernetesService->applyManifest($server, $namespace, $ingressManifest, $ingressName);
            return true;
        } catch (Exception $e) {
            Log::error("Failed to create Kubernetes ingress: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Setup complete isolation for deployment
     */
    public function setupCompleteIsolation(GitDeployment $deployment): bool
    {
        // Try Kubernetes first if enabled
        if (config('kubernetes.enabled', false)) {
            $podCreated = $this->createKubernetesPod($deployment);
            if ($podCreated) {
                $this->createKubernetesService($deployment);
                $this->createKubernetesIngress($deployment);
                return true;
            }
        }

        // Fallback to Docker container
        $container = $this->createIsolatedContainer($deployment);
        return $container !== null;
    }

    /**
     * Generate container name for domain
     */
    protected function generateContainerName(Domain $domain): string
    {
        $sanitized = preg_replace('/[^a-z0-9\-]/', '-', strtolower($domain->domain_name));
        return "web-{$sanitized}-" . substr(md5($domain->id), 0, 8);
    }

    /**
     * Generate pod name for domain
     */
    protected function generatePodName(Domain $domain): string
    {
        $sanitized = preg_replace('/[^a-z0-9\-]/', '-', strtolower($domain->domain_name));
        $sanitized = substr($sanitized, 0, 50); // Kubernetes name length limit
        return "web-{$sanitized}-" . substr(md5($domain->id), 0, 8);
    }

    /**
     * Generate PVC name for domain
     */
    protected function generatePvcName(Domain $domain): string
    {
        $sanitized = preg_replace('/[^a-z0-9\-]/', '-', strtolower($domain->domain_name));
        return "pvc-{$sanitized}-" . substr(md5($domain->id), 0, 8);
    }

    /**
     * Get namespace for domain
     */
    protected function getNamespaceForDomain(Domain $domain): string
    {
        $prefix = config('kubernetes.namespace_prefix', 'hosting-');
        $sanitized = preg_replace('/[^a-z0-9\-]/', '-', strtolower($domain->domain_name));
        return $prefix . substr($sanitized, 0, 40);
    }

    /**
     * Sanitize label value for Kubernetes
     */
    protected function sanitizeLabelValue(string $value): string
    {
        // Kubernetes labels must be alphanumeric, '-', '_' or '.', max 63 chars
        $sanitized = preg_replace('/[^a-z0-9\-_.]/', '-', strtolower($value));
        return substr($sanitized, 0, 63);
    }

    /**
     * Get web server Docker image
     */
    protected function getWebServerImage(): string
    {
        return config('docker.web_image', 'nginx:alpine');
    }
}
