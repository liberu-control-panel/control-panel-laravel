<?php

namespace App\Services\CloudProvider;

/**
 * Amazon Elastic Kubernetes Service (EKS) Auto-Scaling Provider
 */
class AwsEksProvider extends BaseKubernetesProvider
{
    public function getName(): string
    {
        return 'aws';
    }

    /**
     * Check if vertical scaling is supported
     * EKS supports VPA when installed
     */
    public function supportsVerticalScaling(): bool
    {
        return true;
    }
}
