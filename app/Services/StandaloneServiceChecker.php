<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class StandaloneServiceChecker
{
    protected StandaloneServiceHelper $helper;
    protected DeploymentDetectionService $detectionService;

    public function __construct(
        StandaloneServiceHelper $helper,
        DeploymentDetectionService $detectionService
    ) {
        $this->helper = $helper;
        $this->detectionService = $detectionService;
    }

    /**
     * Check if running in standalone mode
     */
    public function isStandaloneMode(): bool
    {
        return $this->detectionService->isStandalone();
    }

    /**
     * Check all required services for standalone installation
     */
    public function checkAllServices(): array
    {
        if (!$this->isStandaloneMode()) {
            return [
                'standalone_mode' => false,
                'message' => 'Not in standalone mode',
            ];
        }

        $services = [
            'nginx' => $this->checkNginx(),
            'php-fpm' => $this->checkPhpFpm(),
            'mysql' => $this->checkMysql(),
            'postgresql' => $this->checkPostgresql(),
            'postfix' => $this->checkPostfix(),
            'dovecot' => $this->checkDovecot(),
            'bind9' => $this->checkBind9(),
            'certbot' => $this->checkCertbot(),
        ];

        $allReady = true;
        foreach ($services as $service => $status) {
            if (!$status['installed'] || !$status['running']) {
                $allReady = false;
            }
        }

        return [
            'standalone_mode' => true,
            'all_services_ready' => $allReady,
            'services' => $services,
        ];
    }

    /**
     * Check NGINX service
     */
    public function checkNginx(): array
    {
        $installed = $this->helper->isServiceInstalled('nginx');
        $running = $installed && $this->helper->isSystemdServiceRunning('nginx');

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'nginx',
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check PHP-FPM service
     */
    public function checkPhpFpm(): array
    {
        // Check for common PHP-FPM versions
        $versions = ['8.3', '8.2', '8.1', '8.4'];
        $installed = false;
        $running = false;
        $availableVersions = [];

        foreach ($versions as $version) {
            $service = "php{$version}-fpm";
            if ($this->helper->isSystemdServiceRunning($service)) {
                $installed = true;
                $running = true;
                $availableVersions[] = $version;
            }
        }

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'php-fpm',
            'available_versions' => $availableVersions,
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check MySQL/MariaDB service
     */
    public function checkMysql(): array
    {
        $installed = $this->helper->isServiceInstalled('mysql') 
                  || $this->helper->isServiceInstalled('mysqld')
                  || $this->helper->isServiceInstalled('mariadb');
        
        $running = $this->helper->isSystemdServiceRunning('mysql')
                || $this->helper->isSystemdServiceRunning('mysqld')
                || $this->helper->isSystemdServiceRunning('mariadb');

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'mysql/mariadb',
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check PostgreSQL service
     */
    public function checkPostgresql(): array
    {
        $installed = $this->helper->isServiceInstalled('psql');
        $running = $installed && $this->helper->isSystemdServiceRunning('postgresql');

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'postgresql',
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check Postfix service
     */
    public function checkPostfix(): array
    {
        $installed = $this->helper->isServiceInstalled('postfix');
        $running = $installed && $this->helper->isSystemdServiceRunning('postfix');

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'postfix',
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check Dovecot service
     */
    public function checkDovecot(): array
    {
        $installed = $this->helper->isServiceInstalled('dovecot');
        $running = $installed && $this->helper->isSystemdServiceRunning('dovecot');

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'dovecot',
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check BIND9 DNS service
     */
    public function checkBind9(): array
    {
        $installed = $this->helper->isServiceInstalled('named') 
                  || $this->helper->isServiceInstalled('bind9');
        
        $running = $this->helper->isSystemdServiceRunning('named')
                || $this->helper->isSystemdServiceRunning('bind9');

        return [
            'installed' => $installed,
            'running' => $running,
            'service_name' => 'bind9',
            'status' => $this->getStatus($installed, $running),
        ];
    }

    /**
     * Check Certbot (Let's Encrypt)
     */
    public function checkCertbot(): array
    {
        $installed = $this->helper->isCertbotInstalled();

        return [
            'installed' => $installed,
            'running' => true, // Certbot is a CLI tool, not a daemon
            'service_name' => 'certbot',
            'status' => $installed ? 'ready' : 'not_installed',
        ];
    }

    /**
     * Get service status
     */
    protected function getStatus(bool $installed, bool $running): string
    {
        if (!$installed) {
            return 'not_installed';
        }
        if (!$running) {
            return 'not_running';
        }
        return 'ready';
    }

    /**
     * Get missing services
     */
    public function getMissingServices(): array
    {
        $check = $this->checkAllServices();
        
        if (!$check['standalone_mode']) {
            return [];
        }

        $missing = [];
        foreach ($check['services'] as $name => $status) {
            if (!$status['installed']) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Get stopped services
     */
    public function getStoppedServices(): array
    {
        $check = $this->checkAllServices();
        
        if (!$check['standalone_mode']) {
            return [];
        }

        $stopped = [];
        foreach ($check['services'] as $name => $status) {
            if ($status['installed'] && !$status['running']) {
                $stopped[] = $name;
            }
        }

        return $stopped;
    }

    /**
     * Generate installation commands for missing services
     */
    public function getInstallationCommands(): array
    {
        $missing = $this->getMissingServices();
        
        if (empty($missing)) {
            return [];
        }

        // Detect OS
        $osInfo = $this->detectOS();
        $commands = [];

        if (in_array($osInfo['os'], ['ubuntu', 'debian'])) {
            $packages = $this->mapServicesToDebianPackages($missing);
            $commands[] = 'sudo apt-get update';
            $commands[] = 'sudo apt-get install -y ' . implode(' ', $packages);
        } elseif (in_array($osInfo['os'], ['rhel', 'centos', 'almalinux', 'rocky'])) {
            $packages = $this->mapServicesToRhelPackages($missing);
            $commands[] = 'sudo dnf install -y ' . implode(' ', $packages);
        }

        return $commands;
    }

    /**
     * Detect operating system
     */
    protected function detectOS(): array
    {
        $result = $this->helper->executeCommand(['cat', '/etc/os-release']);
        
        $os = 'unknown';
        $version = 'unknown';
        
        if ($result['success']) {
            if (preg_match('/^ID=(.*)$/m', $result['output'], $matches)) {
                $os = trim($matches[1], '"');
            }
            if (preg_match('/^VERSION_ID=(.*)$/m', $result['output'], $matches)) {
                $version = trim($matches[1], '"');
            }
        }

        return [
            'os' => $os,
            'version' => $version,
        ];
    }

    /**
     * Map services to Debian/Ubuntu packages
     */
    protected function mapServicesToDebianPackages(array $services): array
    {
        $map = [
            'nginx' => 'nginx',
            'php-fpm' => 'php8.3-fpm',
            'mysql' => 'mariadb-server',
            'postgresql' => 'postgresql',
            'postfix' => 'postfix',
            'dovecot' => 'dovecot-core dovecot-imapd dovecot-pop3d',
            'bind9' => 'bind9',
            'certbot' => 'certbot python3-certbot-nginx',
        ];

        $packages = [];
        foreach ($services as $service) {
            if (isset($map[$service])) {
                $packages[] = $map[$service];
            }
        }

        return $packages;
    }

    /**
     * Map services to RHEL/CentOS packages
     */
    protected function mapServicesToRhelPackages(array $services): array
    {
        $map = [
            'nginx' => 'nginx',
            'php-fpm' => 'php-fpm',
            'mysql' => 'mariadb-server',
            'postgresql' => 'postgresql-server',
            'postfix' => 'postfix',
            'dovecot' => 'dovecot',
            'bind9' => 'bind',
            'certbot' => 'certbot python3-certbot-nginx',
        ];

        $packages = [];
        foreach ($services as $service) {
            if (isset($map[$service])) {
                $packages[] = $map[$service];
            }
        }

        return $packages;
    }
}
