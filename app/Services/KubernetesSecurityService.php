<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Exception;

class KubernetesSecurityService
{
    protected SshConnectionService $sshService;

    public function __construct(SshConnectionService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Apply RBAC policies for a namespace
     */
    public function applyRbacPolicies(Server $server, string $namespace, Domain $domain): bool
    {
        try {
            // Create ServiceAccount
            $serviceAccount = [
                'apiVersion' => 'v1',
                'kind' => 'ServiceAccount',
                'metadata' => [
                    'name' => 'hosting-user',
                    'namespace' => $namespace,
                ],
            ];

            // Create Role with limited permissions
            $role = [
                'apiVersion' => 'rbac.authorization.k8s.io/v1',
                'kind' => 'Role',
                'metadata' => [
                    'name' => 'hosting-user-role',
                    'namespace' => $namespace,
                ],
                'rules' => [
                    [
                        'apiGroups' => [''],
                        'resources' => ['pods', 'pods/log'],
                        'verbs' => ['get', 'list'],
                    ],
                    [
                        'apiGroups' => [''],
                        'resources' => ['services'],
                        'verbs' => ['get', 'list'],
                    ],
                ],
            ];

            // Create RoleBinding
            $roleBinding = [
                'apiVersion' => 'rbac.authorization.k8s.io/v1',
                'kind' => 'RoleBinding',
                'metadata' => [
                    'name' => 'hosting-user-binding',
                    'namespace' => $namespace,
                ],
                'subjects' => [
                    [
                        'kind' => 'ServiceAccount',
                        'name' => 'hosting-user',
                        'namespace' => $namespace,
                    ],
                ],
                'roleRef' => [
                    'kind' => 'Role',
                    'name' => 'hosting-user-role',
                    'apiGroup' => 'rbac.authorization.k8s.io',
                ],
            ];

            $this->applyManifest($server, $namespace, $serviceAccount, 'serviceaccount');
            $this->applyManifest($server, $namespace, $role, 'role');
            $this->applyManifest($server, $namespace, $roleBinding, 'rolebinding');

            Log::info("Successfully applied RBAC policies for {$namespace}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to apply RBAC policies: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply NetworkPolicy for namespace isolation
     */
    public function applyNetworkPolicies(Server $server, string $namespace, Domain $domain): bool
    {
        if (!config('kubernetes.security.enable_network_policies', true)) {
            return true;
        }

        try {
            // Default deny all ingress traffic
            $denyAllIngress = [
                'apiVersion' => 'networking.k8s.io/v1',
                'kind' => 'NetworkPolicy',
                'metadata' => [
                    'name' => 'deny-all-ingress',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'podSelector' => [],
                    'policyTypes' => ['Ingress'],
                ],
            ];

            // Allow ingress from ingress controller
            $allowIngressController = [
                'apiVersion' => 'networking.k8s.io/v1',
                'kind' => 'NetworkPolicy',
                'metadata' => [
                    'name' => 'allow-ingress-controller',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'podSelector' => [
                        'matchLabels' => [
                            'app' => $this->sanitizeName($domain->domain_name),
                        ],
                    ],
                    'policyTypes' => ['Ingress'],
                    'ingress' => [
                        [
                            'from' => [
                                [
                                    'namespaceSelector' => [
                                        'matchLabels' => [
                                            'name' => 'ingress-nginx',
                                        ],
                                    ],
                                ],
                            ],
                            'ports' => [
                                ['protocol' => 'TCP', 'port' => 80],
                                ['protocol' => 'TCP', 'port' => 443],
                            ],
                        ],
                    ],
                ],
            ];

            // Allow internal communication within namespace
            $allowInternal = [
                'apiVersion' => 'networking.k8s.io/v1',
                'kind' => 'NetworkPolicy',
                'metadata' => [
                    'name' => 'allow-internal',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'podSelector' => [],
                    'policyTypes' => ['Ingress'],
                    'ingress' => [
                        [
                            'from' => [
                                [
                                    'podSelector' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // Allow DNS
            $allowDns = [
                'apiVersion' => 'networking.k8s.io/v1',
                'kind' => 'NetworkPolicy',
                'metadata' => [
                    'name' => 'allow-dns',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'podSelector' => [],
                    'policyTypes' => ['Egress'],
                    'egress' => [
                        [
                            'to' => [
                                [
                                    'namespaceSelector' => [
                                        'matchLabels' => [
                                            'name' => 'kube-system',
                                        ],
                                    ],
                                ],
                            ],
                            'ports' => [
                                ['protocol' => 'UDP', 'port' => 53],
                                ['protocol' => 'TCP', 'port' => 53],
                            ],
                        ],
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $denyAllIngress, 'netpol-deny-ingress');
            $this->applyManifest($server, $namespace, $allowIngressController, 'netpol-allow-ingress');
            $this->applyManifest($server, $namespace, $allowInternal, 'netpol-allow-internal');
            $this->applyManifest($server, $namespace, $allowDns, 'netpol-allow-dns');

            Log::info("Successfully applied NetworkPolicies for {$namespace}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to apply NetworkPolicies: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply PodSecurityPolicy
     */
    public function applyPodSecurityPolicy(Server $server, string $namespace): bool
    {
        if (!config('kubernetes.security.enable_pod_security_policies', true)) {
            return true;
        }

        try {
            // Create restricted PSP
            $psp = [
                'apiVersion' => 'policy/v1beta1',
                'kind' => 'PodSecurityPolicy',
                'metadata' => [
                    'name' => "restricted-{$namespace}",
                ],
                'spec' => [
                    'privileged' => false,
                    'allowPrivilegeEscalation' => false,
                    'requiredDropCapabilities' => ['ALL'],
                    'volumes' => [
                        'configMap',
                        'emptyDir',
                        'projected',
                        'secret',
                        'downwardAPI',
                        'persistentVolumeClaim',
                    ],
                    'hostNetwork' => false,
                    'hostIPC' => false,
                    'hostPID' => false,
                    'runAsUser' => [
                        'rule' => 'MustRunAsNonRoot',
                    ],
                    'seLinux' => [
                        'rule' => 'RunAsAny',
                    ],
                    'supplementalGroups' => [
                        'rule' => 'RunAsAny',
                    ],
                    'fsGroup' => [
                        'rule' => 'RunAsAny',
                    ],
                    'readOnlyRootFilesystem' => config('kubernetes.security.read_only_root_filesystem', false),
                ],
            ];

            $this->applyManifest($server, $namespace, $psp, 'psp', true);

            Log::info("Successfully applied PodSecurityPolicy for {$namespace}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to apply PodSecurityPolicy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply LimitRange to restrict resource usage
     */
    public function applyLimitRange(Server $server, string $namespace): bool
    {
        try {
            $limitRange = [
                'apiVersion' => 'v1',
                'kind' => 'LimitRange',
                'metadata' => [
                    'name' => 'resource-limits',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'limits' => [
                        [
                            'type' => 'Container',
                            'default' => [
                                'cpu' => '500m',
                                'memory' => '512Mi',
                            ],
                            'defaultRequest' => [
                                'cpu' => '100m',
                                'memory' => '128Mi',
                            ],
                            'max' => [
                                'cpu' => '2',
                                'memory' => '2Gi',
                            ],
                        ],
                        [
                            'type' => 'PersistentVolumeClaim',
                            'max' => [
                                'storage' => '50Gi',
                            ],
                        ],
                    ],
                ],
            ];

            $this->applyManifest($server, $namespace, $limitRange, 'limitrange');

            Log::info("Successfully applied LimitRange for {$namespace}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to apply LimitRange: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply ResourceQuota to limit total resources
     */
    public function applyResourceQuota(Server $server, string $namespace, array $quotas = []): bool
    {
        try {
            $defaultQuotas = [
                'requests.cpu' => '2',
                'requests.memory' => '4Gi',
                'limits.cpu' => '4',
                'limits.memory' => '8Gi',
                'persistentvolumeclaims' => '10',
                'pods' => '20',
                'services' => '10',
            ];

            $resourceQuota = [
                'apiVersion' => 'v1',
                'kind' => 'ResourceQuota',
                'metadata' => [
                    'name' => 'namespace-quota',
                    'namespace' => $namespace,
                ],
                'spec' => [
                    'hard' => array_merge($defaultQuotas, $quotas),
                ],
            ];

            $this->applyManifest($server, $namespace, $resourceQuota, 'resourcequota');

            Log::info("Successfully applied ResourceQuota for {$namespace}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to apply ResourceQuota: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply all security policies for a domain
     */
    public function applyAllSecurityPolicies(Server $server, string $namespace, Domain $domain): bool
    {
        try {
            $this->applyRbacPolicies($server, $namespace, $domain);
            $this->applyNetworkPolicies($server, $namespace, $domain);
            $this->applyLimitRange($server, $namespace);
            $this->applyResourceQuota($server, $namespace);

            Log::info("Successfully applied all security policies for {$namespace}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to apply security policies: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply manifest to Kubernetes cluster
     */
    protected function applyManifest(Server $server, string $namespace, array $manifest, string $name, bool $clusterScoped = false): void
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
            
            if ($clusterScoped) {
                $command = "{$kubectlPath} apply -f {$tempFile}";
            } else {
                $command = "{$kubectlPath} apply -f {$tempFile} -n {$namespace}";
            }
            
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
