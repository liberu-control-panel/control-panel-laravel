<?php

namespace App\Services\CloudProvider;

/**
 * Azure Kubernetes Service (AKS) Auto-Scaling Provider
 */
class AzureAksProvider extends BaseKubernetesProvider
{
    public function getName(): string
    {
        return 'azure';
    }

    /**
     * Check if vertical scaling is supported
     * AKS supports VPA when addon is enabled
     */
    public function supportsVerticalScaling(): bool
    {
        return true;
    }
}
