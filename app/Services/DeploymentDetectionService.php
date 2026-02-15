<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DeploymentDetectionService
{
    const MODE_STANDALONE = 'standalone';
    const MODE_DOCKER_COMPOSE = 'docker-compose';
    const MODE_KUBERNETES = 'kubernetes';

    const PROVIDER_UNKNOWN = 'unknown';
    const PROVIDER_AWS = 'aws';
    const PROVIDER_AZURE = 'azure';
    const PROVIDER_GCP = 'gcp';
    const PROVIDER_DIGITALOCEAN = 'digitalocean';
    const PROVIDER_OVH = 'ovh';
    const PROVIDER_LINODE = 'linode';
    const PROVIDER_VULTR = 'vultr';
    const PROVIDER_HETZNER = 'hetzner';
    const PROVIDER_ON_PREMISE = 'on-premise';

    /**
     * Detect the current deployment mode
     */
    public function detectDeploymentMode(): string
    {
        // Check if running in Kubernetes
        if ($this->isKubernetes()) {
            return self::MODE_KUBERNETES;
        }

        // Check if running in Docker
        if ($this->isDocker()) {
            return self::MODE_DOCKER_COMPOSE;
        }

        // Default to standalone
        return self::MODE_STANDALONE;
    }

    /**
     * Check if running in Kubernetes
     */
    public function isKubernetes(): bool
    {
        // Check for Kubernetes service account
        if (File::exists('/var/run/secrets/kubernetes.io/serviceaccount')) {
            return true;
        }

        // Check for KUBERNETES_SERVICE_HOST environment variable
        if (env('KUBERNETES_SERVICE_HOST')) {
            return true;
        }

        // Check if kubectl is available and can connect to cluster
        if (File::exists('/usr/local/bin/kubectl') || File::exists('/usr/bin/kubectl')) {
            try {
                $output = shell_exec('kubectl cluster-info 2>&1');
                if ($output && stripos($output, 'Kubernetes') !== false) {
                    return true;
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return false;
    }

    /**
     * Check if running in Docker
     */
    public function isDocker(): bool
    {
        // Check for .dockerenv file
        if (File::exists('/.dockerenv')) {
            return true;
        }

        // Check cgroup for docker
        if (File::exists('/proc/1/cgroup')) {
            $cgroup = File::get('/proc/1/cgroup');
            if (stripos($cgroup, 'docker') !== false) {
                return true;
            }
        }

        // Check environment variable
        if (env('DOCKER_ENVIRONMENT', false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if running in standalone mode
     */
    public function isStandalone(): bool
    {
        return !$this->isKubernetes() && !$this->isDocker();
    }

    /**
     * Detect cloud provider
     */
    public function detectCloudProvider(): string
    {
        // Try Kubernetes-based detection first
        if ($this->isKubernetes()) {
            $provider = $this->detectK8sCloudProvider();
            if ($provider !== self::PROVIDER_UNKNOWN) {
                return $provider;
            }
        }

        // Try instance metadata services
        $provider = $this->detectViaMetadata();
        if ($provider !== self::PROVIDER_UNKNOWN) {
            return $provider;
        }

        // Check environment variables
        $provider = $this->detectViaEnvironment();
        if ($provider !== self::PROVIDER_UNKNOWN) {
            return $provider;
        }

        // Check for on-premise indicators
        if ($this->isOnPremise()) {
            return self::PROVIDER_ON_PREMISE;
        }

        return self::PROVIDER_UNKNOWN;
    }

    /**
     * Detect cloud provider via Kubernetes node labels
     */
    protected function detectK8sCloudProvider(): string
    {
        try {
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            
            if (!File::exists($kubectlPath)) {
                return self::PROVIDER_UNKNOWN;
            }

            // Get node information
            $output = shell_exec("{$kubectlPath} get nodes -o json 2>&1");
            
            if (!$output) {
                return self::PROVIDER_UNKNOWN;
            }

            $data = json_decode($output, true);
            
            if (!isset($data['items'][0]['metadata']['labels'])) {
                return self::PROVIDER_UNKNOWN;
            }

            $labels = $data['items'][0]['metadata']['labels'];

            // Check for AKS (Azure)
            if (isset($labels['kubernetes.azure.com/cluster']) || 
                isset($labels['agentpool'])) {
                return self::PROVIDER_AZURE;
            }

            // Check for EKS (AWS)
            if (isset($labels['eks.amazonaws.com/nodegroup']) || 
                isset($labels['node.kubernetes.io/instance-type']) && 
                stripos($labels['node.kubernetes.io/instance-type'], 'aws') !== false) {
                return self::PROVIDER_AWS;
            }

            // Check for GKE (Google Cloud)
            if (isset($labels['cloud.google.com/gke-nodepool']) || 
                isset($labels['cloud.google.com/gke-os-distribution'])) {
                return self::PROVIDER_GCP;
            }

            // Check for DOKS (DigitalOcean)
            if (isset($labels['doks.digitalocean.com/node-pool']) || 
                isset($labels['region'])) {
                $providerID = $data['items'][0]['spec']['providerID'] ?? '';
                if (stripos($providerID, 'digitalocean') !== false) {
                    return self::PROVIDER_DIGITALOCEAN;
                }
            }

        } catch (\Exception $e) {
            Log::debug('Error detecting K8s cloud provider: ' . $e->getMessage());
        }

        return self::PROVIDER_UNKNOWN;
    }

    /**
     * Detect cloud provider via instance metadata
     */
    protected function detectViaMetadata(): string
    {
        // AWS metadata service
        if ($this->checkMetadataEndpoint('http://169.254.169.254/latest/meta-data/', 1)) {
            return self::PROVIDER_AWS;
        }

        // Azure metadata service
        if ($this->checkMetadataEndpoint('http://169.254.169.254/metadata/instance?api-version=2021-02-01', 1, ['Metadata: true'])) {
            return self::PROVIDER_AZURE;
        }

        // Google Cloud metadata service
        if ($this->checkMetadataEndpoint('http://metadata.google.internal/computeMetadata/v1/', 1, ['Metadata-Flavor: Google'])) {
            return self::PROVIDER_GCP;
        }

        // DigitalOcean metadata service
        if ($this->checkMetadataEndpoint('http://169.254.169.254/metadata/v1/', 1)) {
            $output = @file_get_contents('http://169.254.169.254/metadata/v1/vendor-data', false, stream_context_create([
                'http' => ['timeout' => 1]
            ]));
            if ($output && stripos($output, 'digitalocean') !== false) {
                return self::PROVIDER_DIGITALOCEAN;
            }
        }

        return self::PROVIDER_UNKNOWN;
    }

    /**
     * Check if metadata endpoint is accessible
     */
    protected function checkMetadataEndpoint(string $url, int $timeout = 1, array $headers = []): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
            ],
        ]);

        try {
            $result = @file_get_contents($url, false, $context);
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Detect cloud provider via environment variables
     */
    protected function detectViaEnvironment(): string
    {
        // Check explicit cloud provider environment variable
        $provider = env('CLOUD_PROVIDER');
        if ($provider) {
            return strtolower($provider);
        }

        // Check for cloud-specific environment variables
        if (env('AWS_REGION') || env('AWS_DEFAULT_REGION')) {
            return self::PROVIDER_AWS;
        }

        if (env('AZURE_TENANT_ID') || env('AZURE_SUBSCRIPTION_ID')) {
            return self::PROVIDER_AZURE;
        }

        if (env('GCP_PROJECT') || env('GOOGLE_CLOUD_PROJECT')) {
            return self::PROVIDER_GCP;
        }

        if (env('DIGITALOCEAN_TOKEN')) {
            return self::PROVIDER_DIGITALOCEAN;
        }

        return self::PROVIDER_UNKNOWN;
    }

    /**
     * Check if deployment is on-premise
     */
    protected function isOnPremise(): bool
    {
        // If we can't detect any cloud provider and we're in K8s or Docker
        // it's likely on-premise
        if ($this->isKubernetes() || $this->isDocker()) {
            return true;
        }

        return false;
    }

    /**
     * Get all deployment information
     */
    public function getDeploymentInfo(): array
    {
        return [
            'mode' => $this->detectDeploymentMode(),
            'cloud_provider' => $this->detectCloudProvider(),
            'is_kubernetes' => $this->isKubernetes(),
            'is_docker' => $this->isDocker(),
            'is_standalone' => $this->isStandalone(),
            'supports_auto_scaling' => $this->supportsAutoScaling(),
        ];
    }

    /**
     * Check if current environment supports auto-scaling
     */
    public function supportsAutoScaling(): bool
    {
        if (!$this->isKubernetes()) {
            return false;
        }

        $provider = $this->detectCloudProvider();
        
        // Most cloud providers support auto-scaling in K8s
        return in_array($provider, [
            self::PROVIDER_AWS,
            self::PROVIDER_AZURE,
            self::PROVIDER_GCP,
            self::PROVIDER_DIGITALOCEAN,
            self::PROVIDER_OVH,
        ]);
    }

    /**
     * Get deployment mode label
     */
    public function getDeploymentModeLabel(string $mode = null): string
    {
        $mode = $mode ?? $this->detectDeploymentMode();
        
        return match($mode) {
            self::MODE_KUBERNETES => 'Kubernetes',
            self::MODE_DOCKER_COMPOSE => 'Docker Compose',
            self::MODE_STANDALONE => 'Standalone',
            default => 'Unknown',
        };
    }

    /**
     * Get cloud provider label
     */
    public function getCloudProviderLabel(string $provider = null): string
    {
        $provider = $provider ?? $this->detectCloudProvider();
        
        return match($provider) {
            self::PROVIDER_AWS => 'Amazon Web Services (AWS)',
            self::PROVIDER_AZURE => 'Microsoft Azure',
            self::PROVIDER_GCP => 'Google Cloud Platform',
            self::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
            self::PROVIDER_OVH => 'OVHcloud',
            self::PROVIDER_LINODE => 'Linode',
            self::PROVIDER_VULTR => 'Vultr',
            self::PROVIDER_HETZNER => 'Hetzner Cloud',
            self::PROVIDER_ON_PREMISE => 'On-Premise',
            default => 'Unknown',
        };
    }
}
