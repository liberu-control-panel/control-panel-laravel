<?php

namespace App\Services\CloudProvider;

use App\Models\Domain;
use App\Models\Server;

interface CloudProviderInterface
{
    /**
     * Get the cloud provider name
     */
    public function getName(): string;

    /**
     * Enable horizontal pod autoscaling for a domain
     */
    public function enableHorizontalScaling(
        Domain $domain,
        int $minReplicas = 1,
        int $maxReplicas = 10,
        int $targetCpuUtilization = 80
    ): bool;

    /**
     * Enable vertical pod autoscaling for a domain
     */
    public function enableVerticalScaling(
        Domain $domain,
        array $options = []
    ): bool;

    /**
     * Disable horizontal scaling for a domain
     */
    public function disableHorizontalScaling(Domain $domain): bool;

    /**
     * Disable vertical scaling for a domain
     */
    public function disableVerticalScaling(Domain $domain): bool;

    /**
     * Get current scaling configuration for a domain
     */
    public function getScalingConfig(Domain $domain): array;

    /**
     * Get current replica count for a domain
     */
    public function getCurrentReplicas(Domain $domain): int;

    /**
     * Check if horizontal scaling is supported
     */
    public function supportsHorizontalScaling(): bool;

    /**
     * Check if vertical scaling is supported
     */
    public function supportsVerticalScaling(): bool;

    /**
     * Manually scale a domain to a specific number of replicas
     */
    public function scaleToReplicas(Domain $domain, int $replicas): bool;

    /**
     * Get resource metrics for a domain
     */
    public function getResourceMetrics(Domain $domain): array;
}
