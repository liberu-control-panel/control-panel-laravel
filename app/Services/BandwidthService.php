<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\ResourceUsage;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BandwidthService
{
    protected DeploymentDetectionService $detectionService;

    public function __construct(DeploymentDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * Record current bandwidth and disk usage for a domain.
     *
     * Reads data from the system/container and upserts a monthly snapshot in
     * the resource_usage table – mirroring what Virtualmin tracks on the
     * "Bandwidth Usage" page.
     */
    public function recordUsage(Domain $domain): ?ResourceUsage
    {
        try {
            $disk      = $this->getDiskUsageMb($domain);
            $bandwidth = $this->getBandwidthUsageMb($domain);

            $usage = ResourceUsage::updateOrCreate(
                [
                    'user_id'   => $domain->user_id,
                    'domain_id' => $domain->id,
                    'month'     => now()->month,
                    'year'      => now()->year,
                ],
                [
                    'disk_usage'      => $disk,
                    'bandwidth_usage' => $bandwidth,
                ]
            );

            return $usage;
        } catch (Exception $e) {
            Log::error("Failed to record usage for {$domain->domain_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Return an array of monthly usage records for a domain.
     *
     * @return \Illuminate\Database\Eloquent\Collection<ResourceUsage>
     */
    public function getMonthlyUsage(Domain $domain, int $months = 12)
    {
        return ResourceUsage::forDomain($domain->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($months)
            ->get();
    }

    /**
     * Check whether the domain has exceeded its hosting plan bandwidth quota.
     */
    public function hasExceededBandwidthQuota(Domain $domain): bool
    {
        $plan = $domain->hostingPlan?->hostingPlan;
        if (!$plan || !$plan->bandwidth) {
            return false;
        }

        $used = ResourceUsage::forDomain($domain->id)
            ->forMonth(now()->month, now()->year)
            ->value('bandwidth_usage') ?? 0;

        // Hosting plan bandwidth is stored in MB
        return $used >= $plan->bandwidth;
    }

    /**
     * Get disk usage for the domain's home directory in MB.
     */
    protected function getDiskUsageMb(Domain $domain): int
    {
        if ($this->detectionService->isStandalone()) {
            $homeDir = "/home/{$domain->domain_name}";
            if (!is_dir($homeDir)) {
                return 0;
            }
            $process = new Process(['du', '-sm', $homeDir]);
            $process->run();
            if ($process->isSuccessful()) {
                return (int) explode("\t", trim($process->getOutput()))[0];
            }
            return 0;
        }

        // Docker: sum container volumes
        $process = new Process([
            'docker', 'exec', "{$domain->domain_name}_web",
            'du', '-sm', '/var/www/html',
        ]);
        $process->run();
        if ($process->isSuccessful()) {
            return (int) explode("\t", trim($process->getOutput()))[0];
        }
        return 0;
    }

    /**
     * Get bandwidth usage for the current month in MB.
     *
     * In standalone mode this parses the Nginx access log; in Docker mode it
     * reads container network stats.
     */
    protected function getBandwidthUsageMb(Domain $domain): int
    {
        if ($this->detectionService->isStandalone()) {
            return $this->parseBandwidthFromAccessLog($domain);
        }

        return $this->readDockerNetworkStats($domain);
    }

    /**
     * Sum bytes_sent in Nginx access logs for the current month.
     */
    protected function parseBandwidthFromAccessLog(Domain $domain): int
    {
        $logPath = "/var/log/nginx/{$domain->domain_name}-access.log";
        if (!file_exists($logPath)) {
            return 0;
        }

        // Filter by year:month portion of the Nginx combined log timestamp (e.g. "2026:03")
        $yearMonthFilter = now()->format('Y:m');

        // Use awk to sum the 10th field (bytes sent) for the current month
        $awk = "awk -v ym=\"{$yearMonthFilter}\" '\$4 ~ ym {sum += \$10} END {print sum}'";
        $process = new Process(['bash', '-c', "{$awk} {$logPath}"]);
        $process->run();

        if ($process->isSuccessful()) {
            $bytes = (int) trim($process->getOutput());
            return (int) ($bytes / 1024 / 1024);
        }

        return 0;
    }

    /**
     * Read cumulative network I/O from a Docker container's stats.
     */
    protected function readDockerNetworkStats(Domain $domain): int
    {
        $containerName = "{$domain->domain_name}_web";
        $process = new Process([
            'docker', 'stats', '--no-stream', '--format',
            '{{.NetIO}}', $containerName,
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            return 0;
        }

        // Output is like "1.5MB / 3.2MB"; we take the sent (second) value
        $parts = explode('/', trim($process->getOutput()));
        return isset($parts[1]) ? $this->parseHumanSize(trim($parts[1])) : 0;
    }

    /**
     * Parse a human-readable size string (e.g. "3.2MB", "512KB") to MB.
     */
    protected function parseHumanSize(string $size): int
    {
        if (preg_match('/^([\d.]+)\s*(B|KB|MB|GB|TB)$/i', $size, $m)) {
            $value = (float) $m[1];
            return match (strtoupper($m[2])) {
                'GB'    => (int) ($value * 1024),
                'TB'    => (int) ($value * 1024 * 1024),
                'KB'    => (int) ($value / 1024),
                'B'     => (int) ($value / 1024 / 1024),
                default => (int) $value,  // MB
            };
        }
        return 0;
    }
}
