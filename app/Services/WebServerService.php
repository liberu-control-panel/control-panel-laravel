<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\Container;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class WebServerService
{
    protected $containerManager;

    public function __construct(ContainerManagerService $containerManager)
    {
        $this->containerManager = $containerManager;
    }

    /**
     * Create Nginx configuration for a domain
     */
    public function createNginxConfig(Domain $domain, array $options = []): string
    {
        $phpVersion = $options['php_version'] ?? '8.2';
        $documentRoot = $options['document_root'] ?? '/var/www/html';
        $enableSSL = $options['enable_ssl'] ?? true;

        $config = $this->generateNginxServerBlock($domain, $phpVersion, $documentRoot, $enableSSL);

        // Save configuration file
        $configPath = "nginx/sites/{$domain->domain_name}.conf";
        Storage::disk('local')->put($configPath, $config);

        return $config;
    }

    /**
     * Generate Nginx server block configuration
     */
    protected function generateNginxServerBlock(Domain $domain, string $phpVersion, string $documentRoot, bool $enableSSL): string
    {
        $domainName = $domain->domain_name;
        $containerName = "{$domainName}_php";

        $config = "server {\n";

        if ($enableSSL) {
            $config .= "    listen 443 ssl http2;\n";
            $config .= "    listen [::]:443 ssl http2;\n";
            $config .= "    ssl_certificate /etc/nginx/certs/{$domainName}.crt;\n";
            $config .= "    ssl_certificate_key /etc/nginx/certs/{$domainName}.key;\n";
            $config .= "    ssl_protocols TLSv1.2 TLSv1.3;\n";
            $config .= "    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;\n";
            $config .= "    ssl_prefer_server_ciphers off;\n";
            $config .= "    ssl_session_cache shared:SSL:10m;\n";
            $config .= "    ssl_session_timeout 10m;\n\n";
        } else {
            $config .= "    listen 80;\n";
            $config .= "    listen [::]:80;\n\n";
        }

        $config .= "    server_name {$domainName} www.{$domainName};\n";
        $config .= "    root {$documentRoot};\n";
        $config .= "    index index.php index.html index.htm;\n\n";

        // Security headers
        $config .= "    # Security headers\n";
        $config .= "    add_header X-Frame-Options \"SAMEORIGIN\" always;\n";
        $config .= "    add_header X-XSS-Protection \"1; mode=block\" always;\n";
        $config .= "    add_header X-Content-Type-Options \"nosniff\" always;\n";
        $config .= "    add_header Referrer-Policy \"no-referrer-when-downgrade\" always;\n";
        $config .= "    add_header Content-Security-Policy \"default-src 'self' http: https: data: blob: 'unsafe-inline'\" always;\n\n";

        // Gzip compression
        $config .= "    # Gzip compression\n";
        $config .= "    gzip on;\n";
        $config .= "    gzip_vary on;\n";
        $config .= "    gzip_min_length 1024;\n";
        $config .= "    gzip_proxied expired no-cache no-store private must-revalidate auth;\n";
        $config .= "    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;\n\n";

        // PHP handling
        $config .= "    # PHP handling\n";
        $config .= "    location ~ \\.php$ {\n";
        $config .= "        try_files \$uri =404;\n";
        $config .= "        fastcgi_split_path_info ^(.+\\.php)(/.+)$;\n";
        $config .= "        fastcgi_pass {$containerName}:9000;\n";
        $config .= "        fastcgi_index index.php;\n";
        $config .= "        include fastcgi_params;\n";
        $config .= "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
        $config .= "        fastcgi_param PATH_INFO \$fastcgi_path_info;\n";
        $config .= "        fastcgi_read_timeout 300;\n";
        $config .= "    }\n\n";

        // Static files
        $config .= "    # Static files\n";
        $config .= "    location ~* \\.(jpg|jpeg|gif|png|css|js|ico|xml)$ {\n";
        $config .= "        expires 1y;\n";
        $config .= "        add_header Cache-Control \"public, immutable\";\n";
        $config .= "        access_log off;\n";
        $config .= "    }\n\n";

        // Deny access to sensitive files
        $config .= "    # Deny access to sensitive files\n";
        $config .= "    location ~ /\\. {\n";
        $config .= "        deny all;\n";
        $config .= "    }\n\n";

        $config .= "    location ~ ~$ {\n";
        $config .= "        deny all;\n";
        $config .= "    }\n\n";

        // WordPress specific rules (if detected)
        $config .= "    # WordPress specific rules\n";
        $config .= "    location / {\n";
        $config .= "        try_files \$uri \$uri/ /index.php?\$args;\n";
        $config .= "    }\n\n";

        $config .= "    # Logging\n";
        $config .= "    access_log /var/log/nginx/{$domainName}_access.log;\n";
        $config .= "    error_log /var/log/nginx/{$domainName}_error.log;\n";

        $config .= "}\n";

        // HTTP to HTTPS redirect if SSL is enabled
        if ($enableSSL) {
            $config .= "\nserver {\n";
            $config .= "    listen 80;\n";
            $config .= "    listen [::]:80;\n";
            $config .= "    server_name {$domainName} www.{$domainName};\n";
            $config .= "    return 301 https://\$server_name\$request_uri;\n";
            $config .= "}\n";
        }

        return $config;
    }

    /**
     * Create PHP-FPM configuration
     */
    public function createPhpFpmConfig(Domain $domain, string $phpVersion = '8.2'): string
    {
        $domainName = $domain->domain_name;
        $poolName = str_replace(['.', '-'], '_', $domainName);

        $config = "[{$poolName}]\n";
        $config .= "user = www-data\n";
        $config .= "group = www-data\n";
        $config .= "listen = 9000\n";
        $config .= "listen.owner = www-data\n";
        $config .= "listen.group = www-data\n";
        $config .= "pm = dynamic\n";
        $config .= "pm.max_children = 20\n";
        $config .= "pm.start_servers = 2\n";
        $config .= "pm.min_spare_servers = 1\n";
        $config .= "pm.max_spare_servers = 3\n";
        $config .= "pm.max_requests = 500\n";
        $config .= "pm.status_path = /status\n";
        $config .= "ping.path = /ping\n";
        $config .= "access.log = /var/log/php-fpm/{$domainName}_access.log\n";
        $config .= "slowlog = /var/log/php-fpm/{$domainName}_slow.log\n";
        $config .= "request_slowlog_timeout = 10s\n";
        $config .= "request_terminate_timeout = 120s\n";
        $config .= "rlimit_files = 1024\n";
        $config .= "rlimit_core = 0\n";

        // Environment variables
        $config .= "env[HOSTNAME] = \$HOSTNAME\n";
        $config .= "env[PATH] = /usr/local/bin:/usr/bin:/bin\n";
        $config .= "env[TMP] = /tmp\n";
        $config .= "env[TMPDIR] = /tmp\n";
        $config .= "env[TEMP] = /tmp\n";

        // Save configuration
        $configPath = "php/{$phpVersion}/pool.d/{$domainName}.conf";
        Storage::disk('local')->put($configPath, $config);

        return $config;
    }

    /**
     * Create subdomain configuration
     */
    public function createSubdomainConfig(Subdomain $subdomain): string
    {
        $domain = $subdomain->domain;
        $fullDomain = $subdomain->full_name;
        $phpVersion = $subdomain->php_version ?? '8.2';
        $documentRoot = $subdomain->document_root ?? '/var/www/html/subdomains/' . $subdomain->subdomain;

        // Handle redirects
        if ($subdomain->redirect_url) {
            return $this->generateRedirectConfig($fullDomain, $subdomain->redirect_url, $subdomain->redirect_type);
        }

        $config = $this->generateNginxServerBlock($domain, $phpVersion, $documentRoot, true);
        $config = str_replace($domain->domain_name, $fullDomain, $config);

        // Save configuration
        $configPath = "nginx/sites/{$fullDomain}.conf";
        Storage::disk('local')->put($configPath, $config);

        return $config;
    }

    /**
     * Generate redirect configuration
     */
    protected function generateRedirectConfig(string $domain, string $redirectUrl, string $redirectType = '301'): string
    {
        $config = "server {\n";
        $config .= "    listen 80;\n";
        $config .= "    listen [::]:80;\n";
        $config .= "    listen 443 ssl http2;\n";
        $config .= "    listen [::]:443 ssl http2;\n";
        $config .= "    server_name {$domain};\n";
        $config .= "    return {$redirectType} {$redirectUrl};\n";
        $config .= "}\n";

        return $config;
    }

    /**
     * Reload Nginx configuration
     */
    public function reloadNginx(Domain $domain): bool
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $process = new Process(['docker', 'exec', $containerName, 'nginx', '-s', 'reload']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::error("Failed to reload Nginx for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test Nginx configuration
     */
    public function testNginxConfig(Domain $domain): array
    {
        try {
            $containerName = "{$domain->domain_name}_web";
            $process = new Process(['docker', 'exec', $containerName, 'nginx', '-t']);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get web server statistics
     */
    public function getWebServerStats(Domain $domain): array
    {
        $containerName = "{$domain->domain_name}_web";

        try {
            // Get Nginx status
            $statusProcess = new Process(['docker', 'exec', $containerName, 'curl', '-s', 'http://localhost/nginx_status']);
            $statusProcess->run();

            // Parse Nginx status
            $statusOutput = $statusProcess->getOutput();
            $stats = $this->parseNginxStatus($statusOutput);

            // Get container resource usage
            $resourceProcess = new Process(['docker', 'stats', '--no-stream', '--format', 'table {{.CPUPerc}}\t{{.MemUsage}}', $containerName]);
            $resourceProcess->run();

            if ($resourceProcess->isSuccessful()) {
                $resourceOutput = trim($resourceProcess->getOutput());
                $lines = explode("\n", $resourceOutput);
                if (count($lines) > 1) {
                    $data = explode("\t", $lines[1]);
                    $stats['cpu_usage'] = trim($data[0] ?? '0%');
                    $stats['memory_usage'] = trim($data[1] ?? '0B / 0B');
                }
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error("Failed to get web server stats for {$domain->domain_name}: " . $e->getMessage());
            return [
                'active_connections' => 0,
                'requests_per_second' => 0,
                'cpu_usage' => '0%',
                'memory_usage' => '0B / 0B'
            ];
        }
    }

    /**
     * Parse Nginx status output
     */
    protected function parseNginxStatus(string $output): array
    {
        $stats = [
            'active_connections' => 0,
            'requests_per_second' => 0,
            'total_requests' => 0
        ];

        if (preg_match('/Active connections: (\d+)/', $output, $matches)) {
            $stats['active_connections'] = (int) $matches[1];
        }

        if (preg_match('/(\d+) (\d+) (\d+)/', $output, $matches)) {
            $stats['total_requests'] = (int) $matches[3];
        }

        return $stats;
    }

    /**
     * Enable/disable maintenance mode
     */
    public function setMaintenanceMode(Domain $domain, bool $enabled): bool
    {
        try {
            $configPath = "nginx/sites/{$domain->domain_name}.conf";

            if ($enabled) {
                // Create maintenance page configuration
                $maintenanceConfig = $this->generateMaintenanceConfig($domain);
                Storage::disk('local')->put($configPath . '.maintenance', $maintenanceConfig);

                // Backup original config and replace with maintenance config
                $originalConfig = Storage::disk('local')->get($configPath);
                Storage::disk('local')->put($configPath . '.backup', $originalConfig);
                Storage::disk('local')->put($configPath, $maintenanceConfig);
            } else {
                // Restore original configuration
                if (Storage::disk('local')->exists($configPath . '.backup')) {
                    $originalConfig = Storage::disk('local')->get($configPath . '.backup');
                    Storage::disk('local')->put($configPath, $originalConfig);
                    Storage::disk('local')->delete($configPath . '.backup');
                    Storage::disk('local')->delete($configPath . '.maintenance');
                }
            }

            return $this->reloadNginx($domain);
        } catch (\Exception $e) {
            Log::error("Failed to set maintenance mode for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate maintenance mode configuration
     */
    protected function generateMaintenanceConfig(Domain $domain): string
    {
        $domainName = $domain->domain_name;

        $config = "server {\n";
        $config .= "    listen 80;\n";
        $config .= "    listen [::]:80;\n";
        $config .= "    listen 443 ssl http2;\n";
        $config .= "    listen [::]:443 ssl http2;\n";
        $config .= "    server_name {$domainName} www.{$domainName};\n";
        $config .= "    root /var/www/maintenance;\n";
        $config .= "    index index.html;\n\n";
        $config .= "    location / {\n";
        $config .= "        return 503;\n";
        $config .= "    }\n\n";
        $config .= "    error_page 503 @maintenance;\n";
        $config .= "    location @maintenance {\n";
        $config .= "        rewrite ^(.*)$ /index.html break;\n";
        $config .= "    }\n";
        $config .= "}\n";

        return $config;
    }
}