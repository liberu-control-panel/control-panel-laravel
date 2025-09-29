<?php

namespace App\Services;

use Exception;
use App\Models\Domain;
use App\Models\Container;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class ContainerManagerService
{
    protected $dockerComposeService;

    public function __construct(DockerComposeService $dockerComposeService)
    {
        $this->dockerComposeService = $dockerComposeService;
    }

    /**
     * Create a complete hosting environment for a domain
     */
    public function createHostingEnvironment(Domain $domain, array $options = []): bool
    {
        try {
            $services = $this->generateServicesConfig($domain, $options);
            $composeContent = $this->generateDockerCompose($domain, $services);

            // Save compose file
            $composeFile = "docker-compose-{$domain->domain_name}.yml";
            Storage::disk('local')->put($composeFile, $composeContent);

            // Create network if it doesn't exist
            $this->createNetwork($domain->domain_name);

            // Start services
            $this->startServices($domain->domain_name);

            // Create container records
            $this->createContainerRecords($domain, $services);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to create hosting environment for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate services configuration based on hosting plan and options
     */
    protected function generateServicesConfig(Domain $domain, array $options): array
    {
        $hostingPlan = $domain->hostingPlan;
        $services = [];

        // Web server (Nginx + PHP-FPM)
        $phpVersion = $options['php_version'] ?? '8.2';
        $services['web'] = [
            'type' => 'web',
            'image' => "nginx:alpine",
            'php_version' => $phpVersion,
            'resources' => [
                'cpu' => $hostingPlan->cpu_limit ?? '0.5',
                'memory' => $hostingPlan->memory_limit ?? '512M'
            ]
        ];

        $services['php'] = [
            'type' => 'php',
            'image' => "php:{$phpVersion}-fpm-alpine",
            'extensions' => $options['php_extensions'] ?? ['mysqli', 'pdo_mysql', 'gd', 'curl', 'zip']
        ];

        // Database (if requested)
        if ($options['database_type'] ?? false) {
            $dbType = $options['database_type'];
            $services['database'] = [
                'type' => 'database',
                'engine' => $dbType,
                'image' => $dbType === 'mysql' ? 'mysql:8.0' : 'postgres:15-alpine',
                'resources' => [
                    'memory' => '256M'
                ]
            ];

            // Database management UI
            $services['db_admin'] = [
                'type' => 'db_admin',
                'image' => $dbType === 'mysql' ? 'phpmyadmin/phpmyadmin' : 'adminer:latest'
            ];
        }

        // File manager
        $services['filemanager'] = [
            'type' => 'filemanager',
            'image' => 'filebrowser/filebrowser:latest'
        ];

        // FTP server
        if ($options['ftp_enabled'] ?? true) {
            $services['ftp'] = [
                'type' => 'ftp',
                'image' => 'stilliard/pure-ftpd:hardened'
            ];
        }

        // Cron service
        $services['cron'] = [
            'type' => 'cron',
            'image' => 'alpine:latest'
        ];

        return $services;
    }

    /**
     * Generate Docker Compose content
     */
    protected function generateDockerCompose(Domain $domain, array $services): string
    {
        $domainName = $domain->domain_name;
        $networkName = "hosting_{$domainName}";

        $compose = [
            'version' => '3.8',
            'services' => [],
            'volumes' => [
                'web_data' => null,
                'db_data' => null,
                'logs' => null
            ],
            'networks' => [
                $networkName => ['driver' => 'bridge'],
                'proxy-network' => ['external' => true]
            ]
        ];

        foreach ($services as $serviceName => $config) {
            $compose['services'][$serviceName] = $this->generateServiceConfig($serviceName, $config, $domain);
        }

        return yaml_emit($compose);
    }

    /**
     * Generate individual service configuration
     */
    protected function generateServiceConfig(string $serviceName, array $config, Domain $domain): array
    {
        $domainName = $domain->domain_name;
        $networkName = "hosting_{$domainName}";

        $serviceConfig = [
            'image' => $config['image'],
            'container_name' => "{$domainName}_{$serviceName}",
            'restart' => 'unless-stopped',
            'networks' => [$networkName]
        ];

        switch ($config['type']) {
            case 'web':
                $serviceConfig = array_merge($serviceConfig, [
                    'environment' => [
                        'VIRTUAL_HOST' => $domainName,
                        'LETSENCRYPT_HOST' => $domainName,
                        'LETSENCRYPT_EMAIL' => $domain->user->email
                    ],
                    'volumes' => [
                        "web_data:/var/www/html",
                        "./nginx/conf.d:/etc/nginx/conf.d:ro"
                    ],
                    'networks' => [$networkName, 'proxy-network'],
                    'depends_on' => ['php']
                ]);
                break;

            case 'php':
                $serviceConfig = array_merge($serviceConfig, [
                    'volumes' => [
                        "web_data:/var/www/html",
                        "./php/php.ini:/usr/local/etc/php/php.ini:ro"
                    ],
                    'environment' => [
                        'PHP_INI_SCAN_DIR' => '/usr/local/etc/php/conf.d:/usr/local/etc/php/custom.d'
                    ]
                ]);
                break;

            case 'database':
                if ($config['engine'] === 'mysql') {
                    $serviceConfig = array_merge($serviceConfig, [
                        'environment' => [
                            'MYSQL_ROOT_PASSWORD' => 'secure_root_password',
                            'MYSQL_DATABASE' => str_replace(['.', '-'], '_', $domainName),
                            'MYSQL_USER' => substr(str_replace(['.', '-'], '_', $domainName), 0, 16),
                            'MYSQL_PASSWORD' => 'secure_user_password'
                        ],
                        'volumes' => ['db_data:/var/lib/mysql']
                    ]);
                }
                break;

            case 'filemanager':
                $serviceConfig = array_merge($serviceConfig, [
                    'environment' => [
                        'VIRTUAL_HOST' => "files.{$domainName}",
                        'VIRTUAL_PORT' => '80',
                        'LETSENCRYPT_HOST' => "files.{$domainName}",
                        'LETSENCRYPT_EMAIL' => $domain->user->email
                    ],
                    'volumes' => [
                        "web_data:/srv",
                        "./filebrowser/config:/config"
                    ],
                    'networks' => [$networkName, 'proxy-network']
                ]);
                break;

            case 'ftp':
                $serviceConfig = array_merge($serviceConfig, [
                    'environment' => [
                        'PUBLICHOST' => $domainName,
                        'FTP_USER_NAME' => $domain->sftp_username,
                        'FTP_USER_PASS' => $domain->sftp_password,
                        'FTP_USER_HOME' => '/home/ftpuser'
                    ],
                    'volumes' => ["web_data:/home/ftpuser"],
                    'ports' => ['21:21', '30000-30009:30000-30009']
                ]);
                break;
        }

        // Add resource limits if specified
        if (isset($config['resources'])) {
            $serviceConfig['deploy'] = [
                'resources' => [
                    'limits' => $config['resources']
                ]
            ];
        }

        return $serviceConfig;
    }

    /**
     * Create Docker network for the domain
     */
    protected function createNetwork(string $domainName): void
    {
        $networkName = "hosting_{$domainName}";
        $process = new Process(['docker', 'network', 'create', $networkName]);
        $process->run();
    }

    /**
     * Start services using Docker Compose
     */
    protected function startServices(string $domainName): void
    {
        $composeFile = storage_path("app/docker-compose-{$domainName}.yml");
        $process = new Process(['docker-compose', '-f', $composeFile, 'up', '-d']);
        $process->run();
    }

    /**
     * Create container records in database
     */
    protected function createContainerRecords(Domain $domain, array $services): void
    {
        foreach ($services as $serviceName => $config) {
            Container::create([
                'domain_id' => $domain->id,
                'name' => $serviceName,
                'type' => $config['type'],
                'image' => $config['image'],
                'container_name' => "{$domain->domain_name}_{$serviceName}",
                'status' => 'running'
            ]);
        }
    }

    /**
     * Stop and remove all containers for a domain
     */
    public function destroyHostingEnvironment(Domain $domain): bool
    {
        try {
            $composeFile = storage_path("app/docker-compose-{$domain->domain_name}.yml");

            if (file_exists($composeFile)) {
                $process = new Process(['docker-compose', '-f', $composeFile, 'down', '-v']);
                $process->run();
            }

            // Remove container records
            Container::where('domain_id', $domain->id)->delete();

            // Remove network
            $networkName = "hosting_{$domain->domain_name}";
            $process = new Process(['docker', 'network', 'rm', $networkName]);
            $process->run();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to destroy hosting environment for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get container status for a domain
     */
    public function getContainerStatus(Domain $domain): array
    {
        $containers = Container::where('domain_id', $domain->id)->get();
        $status = [];

        foreach ($containers as $container) {
            $process = new Process(['docker', 'inspect', '--format={{.State.Status}}', $container->container_name]);
            $process->run();

            $status[$container->name] = [
                'status' => trim($process->getOutput()) ?: 'unknown',
                'type' => $container->type,
                'image' => $container->image
            ];
        }

        return $status;
    }

    /**
     * Restart specific service for a domain
     */
    public function restartService(Domain $domain, string $serviceName): bool
    {
        try {
            $composeFile = storage_path("app/docker-compose-{$domain->domain_name}.yml");
            $process = new Process(['docker-compose', '-f', $composeFile, 'restart', $serviceName]);
            $process->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            Log::error("Failed to restart service {$serviceName} for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }
}