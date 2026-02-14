<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class KubernetesService
{
    protected SshConnectionService $sshService;
    protected ?KubernetesSecurityService $securityService = null;

    public function __construct(SshConnectionService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Set security service (optional dependency injection)
     */
    public function setSecurityService(KubernetesSecurityService $securityService): void
    {
        $this->securityService = $securityService;
    }

    /**
     * Deploy a domain's hosting environment to Kubernetes
     */
    public function deployDomain(Domain $domain, array $options = []): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                throw new Exception("No server available for deployment");
            }

            if (!$server->isKubernetes()) {
                throw new Exception("Server {$server->name} is not a Kubernetes server");
            }

            $namespace = $this->getNamespace($domain);
            $manifests = $this->generateManifests($domain, $options);

            // Create namespace if it doesn't exist
            $this->createNamespace($server, $namespace);

            // Apply security policies if security service is available
            if ($this->securityService) {
                $this->securityService->applyAllSecurityPolicies($server, $namespace, $domain);
            }

            // Apply manifests
            foreach ($manifests as $name => $manifest) {
                $this->applyManifest($server, $namespace, $manifest, $name);
            }

            Log::info("Successfully deployed domain {$domain->domain_name} to Kubernetes");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to deploy domain {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate Kubernetes manifests for a domain
     */
    public function generateManifests(Domain $domain, array $options = []): array
    {
        $manifests = [];
        $namespace = $this->getNamespace($domain);
        $hostingPlan = $domain->hostingPlan;

        // Deployment for web server (NGINX + PHP-FPM)
        $manifests['deployment'] = $this->generateDeployment($domain, $options);

        // Service for web server
        $manifests['service'] = $this->generateService($domain);

        // Ingress for domain
        $manifests['ingress'] = $this->generateIngress($domain);

        // ConfigMap for NGINX configuration
        $manifests['configmap-nginx'] = $this->generateNginxConfigMap($domain);

        // PersistentVolumeClaim for web files
        $manifests['pvc'] = $this->generatePVC($domain);

        // If database is required
        if ($options['database_type'] ?? false) {
            $manifests['statefulset-db'] = $this->generateDatabaseStatefulSet($domain, $options['database_type']);
            $manifests['service-db'] = $this->generateDatabaseService($domain);
            $manifests['pvc-db'] = $this->generateDatabasePVC($domain);
        }

        // Secret for credentials
        $manifests['secret'] = $this->generateSecret($domain);

        return $manifests;
    }

    /**
     * Generate Deployment manifest
     */
    protected function generateDeployment(Domain $domain, array $options): array
    {
        $namespace = $this->getNamespace($domain);
        $hostingPlan = $domain->hostingPlan;
        $phpVersion = $options['php_version'] ?? '8.2';

        $resources = [
            'requests' => [
                'memory' => config('kubernetes.default_resources.requests.memory', '128Mi'),
                'cpu' => config('kubernetes.default_resources.requests.cpu', '100m'),
            ],
            'limits' => [
                'memory' => $hostingPlan->memory_limit ?? config('kubernetes.default_resources.limits.memory', '512Mi'),
                'cpu' => $hostingPlan->cpu_limit ?? config('kubernetes.default_resources.limits.cpu', '500m'),
            ],
        ];

        return [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name),
                'namespace' => $namespace,
                'labels' => [
                    'app' => $this->sanitizeName($domain->domain_name),
                    'domain' => $domain->domain_name,
                ],
            ],
            'spec' => [
                'replicas' => 1,
                'selector' => [
                    'matchLabels' => [
                        'app' => $this->sanitizeName($domain->domain_name),
                    ],
                ],
                'template' => [
                    'metadata' => [
                        'labels' => [
                            'app' => $this->sanitizeName($domain->domain_name),
                        ],
                    ],
                    'spec' => [
                        'securityContext' => $this->getSecurityContext(),
                        'containers' => [
                            // NGINX container
                            [
                                'name' => 'nginx',
                                'image' => config('kubernetes.images.nginx'),
                                'ports' => [
                                    ['containerPort' => 80],
                                ],
                                'volumeMounts' => [
                                    [
                                        'name' => 'web-data',
                                        'mountPath' => '/usr/share/nginx/html',
                                    ],
                                    [
                                        'name' => 'nginx-config',
                                        'mountPath' => '/etc/nginx/conf.d',
                                    ],
                                ],
                                'resources' => $resources,
                            ],
                            // PHP-FPM container
                            [
                                'name' => 'php-fpm',
                                'image' => config('kubernetes.images.php_fpm', "php:{$phpVersion}-fpm-alpine"),
                                'volumeMounts' => [
                                    [
                                        'name' => 'web-data',
                                        'mountPath' => '/var/www/html',
                                    ],
                                ],
                                'resources' => $resources,
                            ],
                        ],
                        'volumes' => [
                            [
                                'name' => 'web-data',
                                'persistentVolumeClaim' => [
                                    'claimName' => $this->sanitizeName($domain->domain_name) . '-web',
                                ],
                            ],
                            [
                                'name' => 'nginx-config',
                                'configMap' => [
                                    'name' => $this->sanitizeName($domain->domain_name) . '-nginx',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate Service manifest
     */
    protected function generateService(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);

        return [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name),
                'namespace' => $namespace,
            ],
            'spec' => [
                'selector' => [
                    'app' => $this->sanitizeName($domain->domain_name),
                ],
                'ports' => [
                    [
                        'port' => 80,
                        'targetPort' => 80,
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate Ingress manifest
     */
    protected function generateIngress(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);
        $ingressClass = config('kubernetes.ingress.class', 'nginx');
        $certIssuer = config('kubernetes.ingress.cert_manager_issuer', 'letsencrypt-prod');

        return [
            'apiVersion' => 'networking.k8s.io/v1',
            'kind' => 'Ingress',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name),
                'namespace' => $namespace,
                'annotations' => [
                    'kubernetes.io/ingress.class' => $ingressClass,
                    'cert-manager.io/cluster-issuer' => $certIssuer,
                    'nginx.ingress.kubernetes.io/ssl-redirect' => 'true',
                ],
            ],
            'spec' => [
                'tls' => [
                    [
                        'hosts' => [$domain->domain_name],
                        'secretName' => $this->sanitizeName($domain->domain_name) . '-tls',
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
                                            'name' => $this->sanitizeName($domain->domain_name),
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
    }

    /**
     * Generate NGINX ConfigMap
     */
    protected function generateNginxConfigMap(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);
        
        $nginxConfig = <<<EOT
server {
    listen 80;
    server_name {$domain->domain_name};
    root /usr/share/nginx/html;
    index index.php index.html index.htm;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass localhost:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOT;

        return [
            'apiVersion' => 'v1',
            'kind' => 'ConfigMap',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name) . '-nginx',
                'namespace' => $namespace,
            ],
            'data' => [
                'default.conf' => $nginxConfig,
            ],
        ];
    }

    /**
     * Generate PersistentVolumeClaim for web files
     */
    protected function generatePVC(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);
        $storageClass = config('kubernetes.storage.class', 'standard');
        $storageSize = $domain->hostingPlan->disk_space ?? config('kubernetes.storage.default_size', '10Gi');

        return [
            'apiVersion' => 'v1',
            'kind' => 'PersistentVolumeClaim',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name) . '-web',
                'namespace' => $namespace,
            ],
            'spec' => [
                'storageClassName' => $storageClass,
                'accessModes' => ['ReadWriteOnce'],
                'resources' => [
                    'requests' => [
                        'storage' => is_numeric($storageSize) ? "{$storageSize}Gi" : $storageSize,
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate Secret for credentials
     */
    protected function generateSecret(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);

        return [
            'apiVersion' => 'v1',
            'kind' => 'Secret',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name) . '-credentials',
                'namespace' => $namespace,
            ],
            'type' => 'Opaque',
            'stringData' => [
                'sftp_username' => $domain->sftp_username ?? '',
                'sftp_password' => $domain->sftp_password ?? '',
                'ssh_username' => $domain->ssh_username ?? '',
                'ssh_password' => $domain->ssh_password ?? '',
            ],
        ];
    }

    /**
     * Generate Database StatefulSet
     */
    protected function generateDatabaseStatefulSet(Domain $domain, string $dbType): array
    {
        $namespace = $this->getNamespace($domain);
        $image = $dbType === 'mysql' 
            ? config('kubernetes.images.mysql') 
            : config('kubernetes.images.postgresql');

        return [
            'apiVersion' => 'apps/v1',
            'kind' => 'StatefulSet',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name) . '-db',
                'namespace' => $namespace,
            ],
            'spec' => [
                'serviceName' => $this->sanitizeName($domain->domain_name) . '-db',
                'replicas' => 1,
                'selector' => [
                    'matchLabels' => [
                        'app' => $this->sanitizeName($domain->domain_name) . '-db',
                    ],
                ],
                'template' => [
                    'metadata' => [
                        'labels' => [
                            'app' => $this->sanitizeName($domain->domain_name) . '-db',
                        ],
                    ],
                    'spec' => [
                        'containers' => [
                            [
                                'name' => $dbType,
                                'image' => $image,
                                'env' => $this->getDatabaseEnv($domain, $dbType),
                                'ports' => [
                                    ['containerPort' => $dbType === 'mysql' ? 3306 : 5432],
                                ],
                                'volumeMounts' => [
                                    [
                                        'name' => 'db-data',
                                        'mountPath' => $dbType === 'mysql' ? '/var/lib/mysql' : '/var/lib/postgresql/data',
                                    ],
                                ],
                            ],
                        ],
                        'volumes' => [
                            [
                                'name' => 'db-data',
                                'persistentVolumeClaim' => [
                                    'claimName' => $this->sanitizeName($domain->domain_name) . '-db',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate Database Service
     */
    protected function generateDatabaseService(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);

        return [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name) . '-db',
                'namespace' => $namespace,
            ],
            'spec' => [
                'selector' => [
                    'app' => $this->sanitizeName($domain->domain_name) . '-db',
                ],
                'ports' => [
                    [
                        'port' => 3306,
                        'targetPort' => 3306,
                    ],
                ],
                'clusterIP' => 'None',
            ],
        ];
    }

    /**
     * Generate Database PVC
     */
    protected function generateDatabasePVC(Domain $domain): array
    {
        $namespace = $this->getNamespace($domain);
        $storageClass = config('kubernetes.storage.class', 'standard');

        return [
            'apiVersion' => 'v1',
            'kind' => 'PersistentVolumeClaim',
            'metadata' => [
                'name' => $this->sanitizeName($domain->domain_name) . '-db',
                'namespace' => $namespace,
            ],
            'spec' => [
                'storageClassName' => $storageClass,
                'accessModes' => ['ReadWriteOnce'],
                'resources' => [
                    'requests' => [
                        'storage' => '5Gi',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get database environment variables
     */
    protected function getDatabaseEnv(Domain $domain, string $dbType): array
    {
        $dbName = $this->sanitizeName($domain->domain_name);
        $dbUser = substr($dbName, 0, 16);
        $dbPassword = bin2hex(random_bytes(16));

        if ($dbType === 'mysql') {
            return [
                ['name' => 'MYSQL_ROOT_PASSWORD', 'value' => bin2hex(random_bytes(16))],
                ['name' => 'MYSQL_DATABASE', 'value' => $dbName],
                ['name' => 'MYSQL_USER', 'value' => $dbUser],
                ['name' => 'MYSQL_PASSWORD', 'value' => $dbPassword],
            ];
        } else {
            return [
                ['name' => 'POSTGRES_DB', 'value' => $dbName],
                ['name' => 'POSTGRES_USER', 'value' => $dbUser],
                ['name' => 'POSTGRES_PASSWORD', 'value' => $dbPassword],
            ];
        }
    }

    /**
     * Get security context for pods
     */
    protected function getSecurityContext(): array
    {
        $security = config('kubernetes.security', []);

        $context = [];

        if ($security['run_as_non_root'] ?? true) {
            $context['runAsNonRoot'] = true;
            $context['runAsUser'] = 1000;
            $context['fsGroup'] = 1000;
        }

        return $context;
    }

    /**
     * Create namespace on Kubernetes cluster
     */
    protected function createNamespace(Server $server, string $namespace): void
    {
        $manifest = [
            'apiVersion' => 'v1',
            'kind' => 'Namespace',
            'metadata' => [
                'name' => $namespace,
            ],
        ];

        $this->applyManifest($server, $namespace, $manifest, 'namespace', true);
    }

    /**
     * Apply a manifest to Kubernetes cluster via SSH
     */
    protected function applyManifest(Server $server, string $namespace, array $manifest, string $name, bool $clusterScoped = false): void
    {
        // Convert manifest to YAML
        $yaml = yaml_emit($manifest);
        
        // Create temporary file for manifest
        $tempFile = "/tmp/k8s-{$name}-" . uniqid() . ".yaml";
        $localTempFile = storage_path("app/temp/{$name}-" . uniqid() . ".yaml");
        
        // Ensure temp directory exists
        if (!is_dir(dirname($localTempFile))) {
            mkdir(dirname($localTempFile), 0755, true);
        }
        
        file_put_contents($localTempFile, $yaml);

        try {
            // Upload manifest to server
            $this->sshService->uploadFile($server, $localTempFile, $tempFile);

            // Apply manifest using kubectl
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            
            if ($clusterScoped) {
                $command = "{$kubectlPath} apply -f {$tempFile}";
            } else {
                $command = "{$kubectlPath} apply -f {$tempFile} -n {$namespace}";
            }

            $result = $this->sshService->execute($server, $command);

            if (!$result['success']) {
                throw new Exception("Failed to apply manifest: " . $result['output']);
            }

            // Clean up temporary file on remote server
            $this->sshService->execute($server, "rm -f {$tempFile}");

        } finally {
            // Clean up local temporary file
            if (file_exists($localTempFile)) {
                unlink($localTempFile);
            }
        }
    }

    /**
     * Delete domain deployment from Kubernetes
     */
    public function deleteDomain(Domain $domain): bool
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                throw new Exception("No server found for domain");
            }

            $namespace = $this->getNamespace($domain);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');

            // Delete all resources in namespace
            $command = "{$kubectlPath} delete namespace {$namespace}";
            $result = $this->sshService->execute($server, $command);

            if (!$result['success']) {
                throw new Exception("Failed to delete namespace: " . $result['output']);
            }

            Log::info("Successfully deleted domain {$domain->domain_name} from Kubernetes");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to delete domain {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get namespace for a domain
     */
    public function getNamespace(Domain $domain): string
    {
        $prefix = config('kubernetes.namespace_prefix', 'hosting-');
        return $prefix . $this->sanitizeName($domain->domain_name);
    }

    /**
     * Sanitize name for Kubernetes (DNS-1123 subdomain)
     */
    protected function sanitizeName(string $name): string
    {
        // Convert to lowercase and replace invalid characters
        $sanitized = strtolower($name);
        $sanitized = preg_replace('/[^a-z0-9-.]/', '-', $sanitized);
        $sanitized = preg_replace('/-+/', '-', $sanitized);
        $sanitized = trim($sanitized, '-.');
        
        // Kubernetes names must be <= 63 characters
        if (strlen($sanitized) > 63) {
            $sanitized = substr($sanitized, 0, 63);
        }
        
        return $sanitized;
    }

    /**
     * Get pod status for a domain
     */
    public function getPodStatus(Domain $domain): array
    {
        try {
            $server = $domain->server ?? Server::getDefault();
            if (!$server) {
                return [];
            }

            $namespace = $this->getNamespace($domain);
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');

            $command = "{$kubectlPath} get pods -n {$namespace} -o json";
            $result = $this->sshService->execute($server, $command);

            if ($result['success']) {
                $data = json_decode($result['output'], true);
                return $data['items'] ?? [];
            }

            return [];

        } catch (Exception $e) {
            Log::error("Failed to get pod status for {$domain->domain_name}: " . $e->getMessage());
            return [];
        }
    }
}
