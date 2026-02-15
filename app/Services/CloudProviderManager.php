<?php

namespace App\Services;

use App\Services\CloudProvider\CloudProviderInterface;
use App\Services\CloudProvider\AzureAksProvider;
use App\Services\CloudProvider\AwsEksProvider;
use App\Services\CloudProvider\GoogleGkeProvider;
use App\Services\CloudProvider\DigitalOceanProvider;
use App\Services\CloudProvider\OvhProvider;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Exception;

class CloudProviderManager
{
    protected DeploymentDetectionService $detectionService;
    protected array $providers = [];

    public function __construct(DeploymentDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * Register a cloud provider
     */
    public function registerProvider(string $name, CloudProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get cloud provider for a server
     */
    public function getProvider(Server $server = null): ?CloudProviderInterface
    {
        // Get the cloud provider from server metadata or detection
        $providerName = $server->metadata['cloud_provider'] ?? null;

        if (!$providerName) {
            // Auto-detect cloud provider
            $providerName = $this->detectionService->detectCloudProvider();
        }

        // Get the provider instance
        return $this->providers[$providerName] ?? null;
    }

    /**
     * Get cloud provider by name
     */
    public function getProviderByName(string $name): ?CloudProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all registered providers
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Check if a provider is registered
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Get current cloud provider name
     */
    public function getCurrentProviderName(): string
    {
        return $this->detectionService->detectCloudProvider();
    }

    /**
     * Check if auto-scaling is available
     */
    public function isAutoScalingAvailable(): bool
    {
        return $this->detectionService->supportsAutoScaling();
    }
}
