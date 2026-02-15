<?php

namespace App\Services\CloudProvider;

/**
 * OVHcloud Kubernetes Auto-Scaling Provider
 */
class OvhProvider extends BaseKubernetesProvider
{
    public function getName(): string
    {
        return 'ovh';
    }

    /**
     * Check if vertical scaling is supported
     * OVH supports VPA when addon is enabled
     */
    public function supportsVerticalScaling(): bool
    {
        return true;
    }
}
