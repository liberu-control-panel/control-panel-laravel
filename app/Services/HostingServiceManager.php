<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Exception;

class HostingServiceManager
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
     * Deploy FTP/SFTP service for a domain
     */
    public function deployFtpService(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server || !$server->isKubernetes()) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            
            $manifest = [
                'apiVersion' => 'apps/v1',
                'kind' => 'Deployment',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-ftp',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'replicas' => 1,
                    'selector' => [
                        'matchLabels' => [
                            'app' => $this->sanitizeName($domain->domain_name) . '-ftp',
                        ],
                    ],
                    'template' => [
                        'metadata' => [
                            'labels' => [
                                'app' => $this->sanitizeName($domain->domain_name) . '-ftp',
                            ],
                        ],
                        'spec' => [
                            'containers' => [
                                [
                                    'name' => 'ftp',
                                    'image' => config('kubernetes.images.ftp'),
                                    'env' => [
                                        ['name' => 'PUBLICHOST', 'value' => $domain->domain_name],
                                        ['name' => 'FTP_USER_NAME', 'value' => $domain->sftp_username],
                                        ['name' => 'FTP_USER_PASS', 'value' => $domain->sftp_password],
                                        ['name' => 'FTP_USER_HOME', 'value' => '/home/ftpuser'],
                                    ],
                                    'ports' => [
                                        ['containerPort' => 21],
                                        ['containerPort' => 30000],
                                    ],
                                    'volumeMounts' => [
                                        [
                                            'name' => 'web-data',
                                            'mountPath' => '/home/ftpuser',
                                        ],
                                    ],
                                ],
                            ],
                            'volumes' => [
                                [
                                    'name' => 'web-data',
                                    'persistentVolumeClaim' => [
                                        'claimName' => $this->sanitizeName($domain->domain_name) . '-web',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // Apply the manifest
            $this->applyManifest($server, $namespace, $manifest, 'ftp-deployment');

            Log::info("Successfully deployed FTP service for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to deploy FTP service: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deploy Redis cache service for a domain
     */
    public function deployRedisService(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server || !$server->isKubernetes()) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            
            // Deployment
            $deployment = [
                'apiVersion' => 'apps/v1',
                'kind' => 'Deployment',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-redis',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'replicas' => 1,
                    'selector' => [
                        'matchLabels' => [
                            'app' => $this->sanitizeName($domain->domain_name) . '-redis',
                        ],
                    ],
                    'template' => [
                        'metadata' => [
                            'labels' => [
                                'app' => $this->sanitizeName($domain->domain_name) . '-redis',
                            ],
                        ],
                        'spec' => [
                            'containers' => [
                                [
                                    'name' => 'redis',
                                    'image' => config('kubernetes.images.redis'),
                                    'ports' => [
                                        ['containerPort' => 6379],
                                    ],
                                    'resources' => [
                                        'limits' => [
                                            'memory' => '256Mi',
                                            'cpu' => '200m',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // Service
            $service = [
                'apiVersion' => 'v1',
                'kind' => 'Service',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-redis',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'selector' => [
                        'app' => $this->sanitizeName($domain->domain_name) . '-redis',
                    ],
                    'ports' => [
                        [
                            'port' => 6379,
                            'targetPort' => 6379,
                        ],
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $deployment, 'redis-deployment');
            $this->applyManifest($server, $namespace, $service, 'redis-service');

            Log::info("Successfully deployed Redis service for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to deploy Redis service: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deploy File Manager service for a domain
     */
    public function deployFileManagerService(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server || !$server->isKubernetes()) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            
            // Deployment
            $deployment = [
                'apiVersion' => 'apps/v1',
                'kind' => 'Deployment',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'replicas' => 1,
                    'selector' => [
                        'matchLabels' => [
                            'app' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                        ],
                    ],
                    'template' => [
                        'metadata' => [
                            'labels' => [
                                'app' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                            ],
                        ],
                        'spec' => [
                            'containers' => [
                                [
                                    'name' => 'filemanager',
                                    'image' => config('kubernetes.images.filemanager'),
                                    'ports' => [
                                        ['containerPort' => 80],
                                    ],
                                    'volumeMounts' => [
                                        [
                                            'name' => 'web-data',
                                            'mountPath' => '/srv',
                                        ],
                                    ],
                                ],
                            ],
                            'volumes' => [
                                [
                                    'name' => 'web-data',
                                    'persistentVolumeClaim' => [
                                        'claimName' => $this->sanitizeName($domain->domain_name) . '-web',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // Service
            $service = [
                'apiVersion' => 'v1',
                'kind' => 'Service',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'selector' => [
                        'app' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                    ],
                    'ports' => [
                        [
                            'port' => 80,
                            'targetPort' => 80,
                        ],
                    ],
                ],
            ];

            // Ingress for File Manager subdomain
            $ingress = [
                'apiVersion' => 'networking.k8s.io/v1',
                'kind' => 'Ingress',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                    'namespace' => $namespace,
                    'annotations' => [
                        'kubernetes.io/ingress.class' => config('kubernetes.ingress.class'),
                        'cert-manager.io/cluster-issuer' => config('kubernetes.ingress.cert_manager_issuer'),
                    ],
                ],
                'spec' => [
                    'tls' => [
                        [
                            'hosts' => ["files.{$domain->domain_name}"],
                            'secretName' => $this->sanitizeName($domain->domain_name) . '-filemanager-tls',
                        ],
                    ],
                    'rules' => [
                        [
                            'host' => "files.{$domain->domain_name}",
                            'http' => [
                                'paths' => [
                                    [
                                        'path' => '/',
                                        'pathType' => 'Prefix',
                                        'backend' => [
                                            'service' => [
                                                'name' => $this->sanitizeName($domain->domain_name) . '-filemanager',
                                                'port' => ['number' => 80],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $deployment, 'filemanager-deployment');
            $this->applyManifest($server, $namespace, $service, 'filemanager-service');
            $this->applyManifest($server, $namespace, $ingress, 'filemanager-ingress');

            Log::info("Successfully deployed File Manager for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to deploy File Manager: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deploy Memcached service for a domain
     */
    public function deployMemcachedService(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server || !$server->isKubernetes()) {
                return false;
            }

            $namespace = $this->kubernetesService->getNamespace($domain);
            
            $deployment = [
                'apiVersion' => 'apps/v1',
                'kind' => 'Deployment',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-memcached',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'replicas' => 1,
                    'selector' => [
                        'matchLabels' => [
                            'app' => $this->sanitizeName($domain->domain_name) . '-memcached',
                        ],
                    ],
                    'template' => [
                        'metadata' => [
                            'labels' => [
                                'app' => $this->sanitizeName($domain->domain_name) . '-memcached',
                            ],
                        ],
                        'spec' => [
                            'containers' => [
                                [
                                    'name' => 'memcached',
                                    'image' => config('kubernetes.images.memcached'),
                                    'ports' => [
                                        ['containerPort' => 11211],
                                    ],
                                    'resources' => [
                                        'limits' => [
                                            'memory' => '128Mi',
                                            'cpu' => '100m',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $service = [
                'apiVersion' => 'v1',
                'kind' => 'Service',
                'metadata' => [
                    'name' => $this->sanitizeName($domain->domain_name) . '-memcached',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'selector' => [
                        'app' => $this->sanitizeName($domain->domain_name) . '-memcached',
                    ],
                    'ports' => [
                        [
                            'port' => 11211,
                            'targetPort' => 11211,
                        ],
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $deployment, 'memcached-deployment');
            $this->applyManifest($server, $namespace, $service, 'memcached-service');

            Log::info("Successfully deployed Memcached service for {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to deploy Memcached service: " . $e->getMessage());
            return false;
        }
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
