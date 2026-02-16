<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class StandaloneServiceHelper
{
    protected DeploymentDetectionService $detectionService;

    public function __construct(DeploymentDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * Check if we should use standalone (native) commands instead of Docker
     */
    public function shouldUseStandaloneMode(): bool
    {
        return $this->detectionService->isStandalone();
    }

    /**
     * Execute a command with proper error handling
     */
    public function executeCommand(array $command, int $timeout = 60): array
    {
        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode()
            ];
        } catch (Exception $e) {
            Log::error("Command execution failed: " . $e->getMessage(), ['command' => $command]);
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1
            ];
        }
    }

    /**
     * Check if a system service is installed
     */
    public function isServiceInstalled(string $service): bool
    {
        $result = $this->executeCommand(['which', $service]);
        return $result['success'];
    }

    /**
     * Check if a systemd service is running
     */
    public function isSystemdServiceRunning(string $service): bool
    {
        $result = $this->executeCommand(['systemctl', 'is-active', $service]);
        return $result['success'] && trim($result['output']) === 'active';
    }

    /**
     * Reload a systemd service
     */
    public function reloadSystemdService(string $service): bool
    {
        $result = $this->executeCommand(['sudo', 'systemctl', 'reload', $service]);
        return $result['success'];
    }

    /**
     * Restart a systemd service
     */
    public function restartSystemdService(string $service): bool
    {
        $result = $this->executeCommand(['sudo', 'systemctl', 'restart', $service]);
        return $result['success'];
    }

    /**
     * Test Nginx configuration
     */
    public function testNginxConfig(): array
    {
        return $this->executeCommand(['sudo', 'nginx', '-t']);
    }

    /**
     * Deploy Nginx configuration file
     */
    public function deployNginxConfig(string $domainName, string $configContent): bool
    {
        try {
            $availablePath = "/etc/nginx/sites-available/{$domainName}";
            $enabledPath = "/etc/nginx/sites-enabled/{$domainName}";

            // Write config to sites-available
            file_put_contents($availablePath, $configContent);
            chmod($availablePath, 0644);

            // Create symlink in sites-enabled if it doesn't exist
            if (!file_exists($enabledPath)) {
                symlink($availablePath, $enabledPath);
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to deploy Nginx config: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove Nginx configuration file
     */
    public function removeNginxConfig(string $domainName): bool
    {
        try {
            $availablePath = "/etc/nginx/sites-available/{$domainName}";
            $enabledPath = "/etc/nginx/sites-enabled/{$domainName}";

            // Remove symlink
            if (is_link($enabledPath)) {
                unlink($enabledPath);
            }

            // Remove config file
            if (file_exists($availablePath)) {
                unlink($availablePath);
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to remove Nginx config: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if Nginx config exists for domain
     */
    public function nginxConfigExists(string $domainName): bool
    {
        $enabledPath = "/etc/nginx/sites-enabled/{$domainName}";
        return file_exists($enabledPath);
    }

    /**
     * Execute MySQL command
     */
    public function executeMysqlCommand(string $command, string $database = null): array
    {
        $mysqlCmd = ['mysql', '-u', 'root'];
        
        // Add password if set
        $password = env('DB_ROOT_PASSWORD', env('DB_PASSWORD'));
        if ($password) {
            $mysqlCmd[] = '-p' . $password;
        }

        if ($database) {
            $mysqlCmd[] = $database;
        }

        $mysqlCmd[] = '-e';
        $mysqlCmd[] = $command;

        return $this->executeCommand($mysqlCmd);
    }

    /**
     * Execute PostgreSQL command
     */
    public function executePostgresCommand(string $command, string $database = 'postgres'): array
    {
        $psqlCmd = ['sudo', '-u', 'postgres', 'psql', '-d', $database, '-c', $command];
        return $this->executeCommand($psqlCmd);
    }

    /**
     * Create MySQL database
     */
    public function createMysqlDatabase(string $name, string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): bool
    {
        $command = "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET {$charset} COLLATE {$collation};";
        $result = $this->executeMysqlCommand($command);
        return $result['success'];
    }

    /**
     * Create PostgreSQL database
     */
    public function createPostgresDatabase(string $name): bool
    {
        $result = $this->executeCommand(['sudo', '-u', 'postgres', 'createdb', $name]);
        return $result['success'];
    }

    /**
     * Drop MySQL database
     */
    public function dropMysqlDatabase(string $name): bool
    {
        $command = "DROP DATABASE IF EXISTS `{$name}`;";
        $result = $this->executeMysqlCommand($command);
        return $result['success'];
    }

    /**
     * Drop PostgreSQL database
     */
    public function dropPostgresDatabase(string $name): bool
    {
        $result = $this->executeCommand(['sudo', '-u', 'postgres', 'dropdb', $name]);
        return $result['success'];
    }

    /**
     * Execute Certbot for Let's Encrypt certificate
     */
    public function executeCertbot(array $domains, string $email, string $webroot = '/var/www/html'): bool
    {
        $cmd = [
            'sudo', 'certbot', 'certonly',
            '--webroot',
            '--webroot-path', $webroot,
            '--email', $email,
            '--agree-tos',
            '--no-eff-email',
            '--force-renewal'
        ];

        foreach ($domains as $domain) {
            $cmd[] = '-d';
            $cmd[] = $domain;
        }

        $result = $this->executeCommand($cmd, 300);
        return $result['success'];
    }

    /**
     * Renew Let's Encrypt certificate
     */
    public function renewCertbotCertificate(string $certName): bool
    {
        $result = $this->executeCommand([
            'sudo', 'certbot', 'renew',
            '--cert-name', $certName,
            '--force-renewal'
        ], 300);

        return $result['success'];
    }

    /**
     * Check if Certbot is installed
     */
    public function isCertbotInstalled(): bool
    {
        return $this->isServiceInstalled('certbot');
    }

    /**
     * Get certificate path for domain
     */
    public function getCertificatePath(string $domainName): array
    {
        return [
            'fullchain' => "/etc/letsencrypt/live/{$domainName}/fullchain.pem",
            'privkey' => "/etc/letsencrypt/live/{$domainName}/privkey.pem",
            'chain' => "/etc/letsencrypt/live/{$domainName}/chain.pem",
            'cert' => "/etc/letsencrypt/live/{$domainName}/cert.pem",
        ];
    }

    /**
     * Check if certificate exists for domain
     */
    public function certificateExists(string $domainName): bool
    {
        $paths = $this->getCertificatePath($domainName);
        return file_exists($paths['fullchain']) && file_exists($paths['privkey']);
    }
}
