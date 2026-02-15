<?php

namespace App\Services\CloudProvider;

/**
 * DigitalOcean Kubernetes (DOKS) Auto-Scaling Provider
 */
class DigitalOceanProvider extends BaseKubernetesProvider
{
    public function getName(): string
    {
        return 'digitalocean';
    }

    /**
     * Check if vertical scaling is supported
     * DOKS supports VPA when installed manually
     */
    public function supportsVerticalScaling(): bool
    {
        return true;
    }
}
