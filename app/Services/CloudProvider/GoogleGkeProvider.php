<?php

namespace App\Services\CloudProvider;

/**
 * Google Kubernetes Engine (GKE) Auto-Scaling Provider
 */
class GoogleGkeProvider extends BaseKubernetesProvider
{
    public function getName(): string
    {
        return 'gcp';
    }

    /**
     * Check if vertical scaling is supported
     * GKE has built-in VPA support
     */
    public function supportsVerticalScaling(): bool
    {
        return true;
    }
}
