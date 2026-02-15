<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Exception;

class HelmChartService
{
    protected SshConnectionService $sshService;
    
    // Available Helm charts
    const CHARTS = [
        'mariadb' => [
            'name' => 'MariaDB',
            'description' => 'MariaDB database cluster with replication',
            'repository' => 'bitnami/mariadb',
            'category' => 'Database',
            'icon' => 'heroicon-o-database',
        ],
        'redis' => [
            'name' => 'Redis',
            'description' => 'Redis cache for sessions and application caching',
            'repository' => 'bitnami/redis',
            'category' => 'Cache',
            'icon' => 'heroicon-o-bolt',
        ],
        'mail-services' => [
            'name' => 'Mail Services',
            'description' => 'Postfix + Dovecot for email (SMTP, IMAP, POP3)',
            'repository' => 'local',
            'path' => './helm/mail-services',
            'category' => 'Email',
            'icon' => 'heroicon-o-envelope',
        ],
        'dns-cluster' => [
            'name' => 'DNS Cluster',
            'description' => 'PowerDNS cluster with multiple nameservers',
            'repository' => 'local',
            'path' => './helm/dns-cluster',
            'category' => 'DNS',
            'icon' => 'heroicon-o-globe-alt',
        ],
        'php-versions' => [
            'name' => 'PHP Multi-Version',
            'description' => 'PHP-FPM versions 8.1 through 8.5',
            'repository' => 'local',
            'path' => './helm/php-versions',
            'category' => 'PHP',
            'icon' => 'heroicon-o-code-bracket',
        ],
        'postgresql' => [
            'name' => 'PostgreSQL',
            'description' => 'PostgreSQL database cluster',
            'repository' => 'bitnami/postgresql',
            'category' => 'Database',
            'icon' => 'heroicon-o-database',
        ],
        'mongodb' => [
            'name' => 'MongoDB',
            'description' => 'MongoDB NoSQL database',
            'repository' => 'bitnami/mongodb',
            'category' => 'Database',
            'icon' => 'heroicon-o-database',
        ],
        'rabbitmq' => [
            'name' => 'RabbitMQ',
            'description' => 'Message broker for queuing',
            'repository' => 'bitnami/rabbitmq',
            'category' => 'Queue',
            'icon' => 'heroicon-o-queue-list',
        ],
        'elasticsearch' => [
            'name' => 'Elasticsearch',
            'description' => 'Search and analytics engine',
            'repository' => 'bitnami/elasticsearch',
            'category' => 'Search',
            'icon' => 'heroicon-o-magnifying-glass',
        ],
    ];

    public function __construct(SshConnectionService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Get available Helm charts
     */
    public function getAvailableCharts(): array
    {
        return self::CHARTS;
    }

    /**
     * Get charts by category
     */
    public function getChartsByCategory(): array
    {
        $categorized = [];
        foreach (self::CHARTS as $key => $chart) {
            $category = $chart['category'];
            if (!isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][$key] = $chart;
        }
        return $categorized;
    }

    /**
     * Check if Helm is installed on server
     */
    public function isHelmInstalled(Server $server): bool
    {
        try {
            $connection = $this->sshService->connect($server);
            $result = $this->sshService->execute($connection, 'helm version --short');
            return str_contains($result, 'v3.');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Install Helm on server
     */
    public function installHelm(Server $server): bool
    {
        try {
            $connection = $this->sshService->connect($server);
            
            $script = <<<'BASH'
curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
helm repo add bitnami https://charts.bitnami.com/bitnami
helm repo update
BASH;

            $this->sshService->execute($connection, $script);
            
            Log::info("Helm installed on server {$server->name}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to install Helm on {$server->name}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Install a Helm chart
     */
    public function installChart(
        Server $server,
        string $chartKey,
        string $releaseName,
        string $namespace = 'default',
        array $values = []
    ): array {
        try {
            if (!isset(self::CHARTS[$chartKey])) {
                throw new Exception("Chart {$chartKey} not found");
            }

            $chart = self::CHARTS[$chartKey];
            $connection = $this->sshService->connect($server);

            // Ensure Helm is installed
            if (!$this->isHelmInstalled($server)) {
                $this->installHelm($server);
            }

            // Build Helm command
            $command = $this->buildInstallCommand(
                $releaseName,
                $chart,
                $namespace,
                $values
            );

            // Execute installation
            $output = $this->sshService->execute($connection, $command);

            Log::info("Installed chart {$chartKey} as {$releaseName} on {$server->name}");

            return [
                'success' => true,
                'message' => "Successfully installed {$chart['name']}",
                'output' => $output,
                'release' => $releaseName,
                'namespace' => $namespace,
            ];
        } catch (Exception $e) {
            Log::error("Failed to install chart {$chartKey}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build Helm install command
     */
    protected function buildInstallCommand(
        string $releaseName,
        array $chart,
        string $namespace,
        array $values
    ): string {
        $parts = [
            'helm upgrade --install',
            escapeshellarg($releaseName),
        ];

        // Add chart source
        if ($chart['repository'] === 'local') {
            $parts[] = escapeshellarg($chart['path']);
        } else {
            $parts[] = escapeshellarg($chart['repository']);
        }

        // Add namespace
        $parts[] = '--namespace ' . escapeshellarg($namespace);
        $parts[] = '--create-namespace';

        // Add values
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                // Handle nested values
                $parts[] = '--set ' . escapeshellarg($key . '=' . json_encode($value));
            } else {
                $parts[] = '--set ' . escapeshellarg("{$key}={$value}");
            }
        }

        // Wait for deployment
        $parts[] = '--wait';
        $parts[] = '--timeout 10m';

        return implode(' ', $parts);
    }

    /**
     * Uninstall a Helm release
     */
    public function uninstallRelease(
        Server $server,
        string $releaseName,
        string $namespace = 'default'
    ): array {
        try {
            $connection = $this->sshService->connect($server);

            $command = sprintf(
                'helm uninstall %s --namespace %s',
                escapeshellarg($releaseName),
                escapeshellarg($namespace)
            );

            $output = $this->sshService->execute($connection, $command);

            Log::info("Uninstalled release {$releaseName} from {$server->name}");

            return [
                'success' => true,
                'message' => "Successfully uninstalled {$releaseName}",
                'output' => $output,
            ];
        } catch (Exception $e) {
            Log::error("Failed to uninstall {$releaseName}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List installed Helm releases
     */
    public function listReleases(Server $server, ?string $namespace = null): array
    {
        try {
            $connection = $this->sshService->connect($server);

            $command = 'helm list --output json';
            if ($namespace) {
                $command .= ' --namespace ' . escapeshellarg($namespace);
            } else {
                $command .= ' --all-namespaces';
            }

            $output = $this->sshService->execute($connection, $command);
            $releases = json_decode($output, true);

            return $releases ?: [];
        } catch (Exception $e) {
            Log::error("Failed to list releases: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get release status
     */
    public function getReleaseStatus(
        Server $server,
        string $releaseName,
        string $namespace = 'default'
    ): ?array {
        try {
            $connection = $this->sshService->connect($server);

            $command = sprintf(
                'helm status %s --namespace %s --output json',
                escapeshellarg($releaseName),
                escapeshellarg($namespace)
            );

            $output = $this->sshService->execute($connection, $command);
            return json_decode($output, true);
        } catch (Exception $e) {
            Log::error("Failed to get release status: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Upgrade a Helm release
     */
    public function upgradeRelease(
        Server $server,
        string $releaseName,
        string $chartKey,
        string $namespace = 'default',
        array $values = []
    ): array {
        // Upgrade is the same as install with --upgrade flag (already in buildInstallCommand)
        return $this->installChart($server, $chartKey, $releaseName, $namespace, $values);
    }

    /**
     * Get default values for a chart
     */
    public function getDefaultValues(string $chartKey): array
    {
        $defaults = [
            'mariadb' => [
                'auth.database' => 'myapp',
                'auth.username' => 'myapp',
                'architecture' => 'replication',
                'secondary.replicaCount' => '2',
                'metrics.enabled' => 'true',
            ],
            'redis' => [
                'auth.enabled' => 'false',
                'replica.replicaCount' => '2',
                'metrics.enabled' => 'true',
            ],
            'mail-services' => [
                'postfix.config.domain' => config('app.domain', 'example.com'),
                'dovecot.persistence.size' => '20Gi',
            ],
            'dns-cluster' => [
                'powerdns.mysql.password' => bin2hex(random_bytes(16)),
                'powerdns.api.key' => bin2hex(random_bytes(16)),
            ],
            'php-versions' => [
                'phpVersions[0].enabled' => 'true',
                'phpVersions[1].enabled' => 'true',
                'phpVersions[2].enabled' => 'true',
            ],
        ];

        return $defaults[$chartKey] ?? [];
    }
}
