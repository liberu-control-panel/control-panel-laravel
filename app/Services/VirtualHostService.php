<?php

namespace App\Services;

use App\Models\VirtualHost;
use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VirtualHostService
{
    protected DeploymentDetectionService $detectionService;
    protected StandaloneServiceHelper $standaloneHelper;

    public function __construct(
        DeploymentDetectionService $detectionService,
        StandaloneServiceHelper $standaloneHelper
    ) {
        $this->detectionService = $detectionService;
        $this->standaloneHelper = $standaloneHelper;
    }

    /**
     * Create a new virtual host with nginx configuration
     */
    public function create(array $data): array
    {
        try {
            $virtualHost = VirtualHost::create([
                'user_id' => $data['user_id'],
                'domain_id' => $data['domain_id'] ?? null,
                'server_id' => $data['server_id'] ?? null,
                'hostname' => $data['hostname'],
                'document_root' => $data['document_root'] ?? '/var/www/html',
                'php_version' => $data['php_version'] ?? '8.3',
                'ssl_enabled' => $data['ssl_enabled'] ?? false,
                'letsencrypt_enabled' => $data['letsencrypt_enabled'] ?? true,
                'port' => $data['port'] ?? 80,
                'status' => VirtualHost::STATUS_PENDING,
            ]);

            // Generate nginx configuration
            $nginxConfig = $this->generateNginxConfig($virtualHost);
            $virtualHost->update(['nginx_config' => $nginxConfig]);

            // Deploy based on environment
            if ($this->detectionService->isStandalone()) {
                $this->deployToStandalone($virtualHost);
            } elseif ($virtualHost->server_id) {
                $this->deployToKubernetes($virtualHost);
            }

            // Request Let's Encrypt certificate if enabled
            if ($virtualHost->letsencrypt_enabled && $virtualHost->ssl_enabled) {
                $this->requestLetsEncryptCertificate($virtualHost);
            }

            $virtualHost->update(['status' => VirtualHost::STATUS_ACTIVE]);

            return [
                'success' => true,
                'virtual_host' => $virtualHost,
                'message' => 'Virtual host created successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Virtual host creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create virtual host: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Derive the system (Linux) username for a virtual host's owner.
     * Only the control-panel service account gets sudo; site users run PHP
     * as their own account via a dedicated PHP-FPM pool.
     *
     * All per-site accounts use a "cp-user-" prefix so that the sudoers
     * rules for chown/useradd can safely be restricted to that pattern.
     */
    protected function getSystemUsername(VirtualHost $virtualHost): string
    {
        $raw = $virtualHost->user->username ?? '';

        // Linux usernames: lowercase, alphanumeric, hyphens and underscores, max 32 chars
        $sanitized = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $raw));
        $sanitized = substr($sanitized, 0, 20); // leave room for the prefix

        // Must not be empty after sanitisation
        if ($sanitized === '') {
            return 'cp-user-' . $virtualHost->user_id;
        }

        // Linux usernames must start with a letter or underscore (not a digit or hyphen)
        if (preg_match('/^[0-9-]/', $sanitized)) {
            $sanitized = 'u' . $sanitized;
        }

        // All per-site accounts use a consistent prefix so they are distinct from
        // system accounts and match the cp-user-* pattern in the sudoers file.
        return substr('cp-user-' . $sanitized, 0, 32);
    }

    /**
     * Generate nginx configuration for virtual host
     */
    protected function generateNginxConfig(VirtualHost $virtualHost): string
    {
        $hostname = $virtualHost->hostname;
        $documentRoot = $virtualHost->document_root;
        $phpVersion = $virtualHost->php_version;
        
        // For standalone, use a per-user unix socket so PHP runs as the site owner;
        // for containers, use a network socket.
        $isStandalone = $this->detectionService->isStandalone();
        if ($isStandalone) {
            $username     = $this->getSystemUsername($virtualHost);
            $phpFpmSocket = 'unix:' . $this->standaloneHelper->getPhpFpmSocketPath($username, $phpVersion);
        } else {
            $phpFpmSocket = "php-versions-" . str_replace('.', '-', $phpVersion) . ":9000";
        }

        $config = "server {\n";
        $config .= "    listen 80;\n";
        
        if ($virtualHost->ssl_enabled) {
            $config .= "    listen 443 ssl http2;\n";
            $config .= "    ssl_certificate /etc/letsencrypt/live/{$hostname}/fullchain.pem;\n";
            $config .= "    ssl_certificate_key /etc/letsencrypt/live/{$hostname}/privkey.pem;\n";
            $config .= "    ssl_protocols TLSv1.2 TLSv1.3;\n";
            $config .= "    ssl_ciphers HIGH:!aNULL:!MD5;\n";
        }
        
        $config .= "    server_name {$hostname};\n";
        $config .= "    root {$documentRoot};\n";
        $config .= "    index index.php index.html index.htm;\n\n";
        
        $config .= "    access_log /var/log/nginx/{$hostname}-access.log;\n";
        $config .= "    error_log /var/log/nginx/{$hostname}-error.log;\n\n";
        
        $config .= "    location / {\n";
        $config .= "        try_files \$uri \$uri/ /index.php?\$query_string;\n";
        $config .= "    }\n\n";
        
        $config .= "    location ~ \\.php$ {\n";
        $config .= "        fastcgi_pass {$phpFpmSocket};\n";
        $config .= "        fastcgi_index index.php;\n";
        $config .= "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
        $config .= "        include fastcgi_params;\n";
        $config .= "    }\n\n";
        
        $config .= "    location ~ /\\.ht {\n";
        $config .= "        deny all;\n";
        $config .= "    }\n";
        $config .= "}\n";

        return $config;
    }

    /**
     * Deploy virtual host to standalone NGINX
     */
    protected function deployToStandalone(VirtualHost $virtualHost): array
    {
        try {
            $hostname = $virtualHost->hostname;
            $nginxConfig = $virtualHost->nginx_config;

            // Derive the system user that will own this virtual host
            $username = $this->getSystemUsername($virtualHost);

            // Create document root directory owned by the site user
            $documentRoot = $virtualHost->document_root;
            $this->standaloneHelper->executeCommand(['sudo', 'mkdir', '-p', $documentRoot]);
            $this->standaloneHelper->executeCommand(['sudo', 'chown', '-R', "{$username}:{$username}", $documentRoot]);
            // Ensure nginx worker can traverse the directory to serve static files
            $this->standaloneHelper->executeCommand(['sudo', 'chmod', '755', $documentRoot]);

            // Create the per-user PHP-FPM pool so PHP runs as the site owner
            $this->standaloneHelper->deployPhpFpmPool($username, $virtualHost->php_version);

            // Deploy nginx configuration
            $this->standaloneHelper->deployNginxConfig($hostname, $nginxConfig);

            // Test nginx configuration
            $testResult = $this->standaloneHelper->testNginxConfig();
            if (!$testResult['success']) {
                throw new \Exception('NGINX configuration test failed: ' . $testResult['error']);
            }

            // Reload nginx
            $this->standaloneHelper->reloadSystemdService('nginx');

            Log::info("Virtual host {$hostname} deployed to standalone NGINX");

            return [
                'success' => true,
                'message' => 'Virtual host deployed to standalone NGINX',
            ];
        } catch (\Exception $e) {
            Log::error('Standalone deployment failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to deploy to standalone NGINX: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Deploy virtual host to Kubernetes with NGINX Ingress
     */
    protected function deployToKubernetes(VirtualHost $virtualHost): array
    {
        try {
            $kubernetesService = app(KubernetesService::class);
            
            // Create Ingress resource
            $ingressManifest = $this->generateIngressManifest($virtualHost);
            
            $result = $kubernetesService->applyManifest(
                $virtualHost->server,
                $ingressManifest,
                'default'
            );

            return [
                'success' => true,
                'message' => 'Virtual host deployed to Kubernetes',
            ];
        } catch (\Exception $e) {
            Log::error('Kubernetes deployment failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to deploy to Kubernetes: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate Kubernetes Ingress manifest
     */
    protected function generateIngressManifest(VirtualHost $virtualHost): string
    {
        $name = Str::slug($virtualHost->hostname);
        $hostname = $virtualHost->hostname;
        
        $manifest = "apiVersion: networking.k8s.io/v1\n";
        $manifest .= "kind: Ingress\n";
        $manifest .= "metadata:\n";
        $manifest .= "  name: {$name}\n";
        $manifest .= "  annotations:\n";
        $manifest .= "    kubernetes.io/ingress.class: nginx\n";
        
        if ($virtualHost->letsencrypt_enabled) {
            $manifest .= "    cert-manager.io/cluster-issuer: letsencrypt-prod\n";
        }
        
        $manifest .= "spec:\n";
        
        if ($virtualHost->ssl_enabled && $virtualHost->letsencrypt_enabled) {
            $manifest .= "  tls:\n";
            $manifest .= "  - hosts:\n";
            $manifest .= "    - {$hostname}\n";
            $manifest .= "    secretName: {$name}-tls\n";
        }
        
        $manifest .= "  rules:\n";
        $manifest .= "  - host: {$hostname}\n";
        $manifest .= "    http:\n";
        $manifest .= "      paths:\n";
        $manifest .= "      - path: /\n";
        $manifest .= "        pathType: Prefix\n";
        $manifest .= "        backend:\n";
        $manifest .= "          service:\n";
        $manifest .= "            name: control-panel\n";
        $manifest .= "            port:\n";
        $manifest .= "              number: 80\n";

        return $manifest;
    }

    /**
     * Request Let's Encrypt SSL certificate
     */
    protected function requestLetsEncryptCertificate(VirtualHost $virtualHost): array
    {
        try {
            if ($this->detectionService->isStandalone()) {
                // Use Certbot for standalone
                $domains = [$virtualHost->hostname];
                $email = config('app.admin_email', 'admin@' . $virtualHost->hostname);
                $webroot = $virtualHost->document_root;
                
                $success = $this->standaloneHelper->executeCertbot($domains, $email, $webroot);
                
                if ($success) {
                    Log::info("Let's Encrypt certificate obtained for {$virtualHost->hostname}");
                    return [
                        'success' => true,
                        'message' => 'Let\'s Encrypt certificate obtained successfully',
                    ];
                } else {
                    throw new \Exception('Certbot execution failed');
                }
            } else {
                // Let's Encrypt certificate is automatically requested by cert-manager
                // via the Kubernetes Ingress annotations
                Log::info("Let's Encrypt certificate requested for {$virtualHost->hostname}");
                
                return [
                    'success' => true,
                    'message' => 'Let\'s Encrypt certificate request submitted',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Let\'s Encrypt certificate request failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to request certificate: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update virtual host configuration
     */
    public function update(VirtualHost $virtualHost, array $data): array
    {
        try {
            $virtualHost->update($data);

            // Regenerate nginx config if relevant fields changed
            if (isset($data['hostname']) || isset($data['document_root']) || isset($data['php_version'])) {
                $nginxConfig = $this->generateNginxConfig($virtualHost);
                $virtualHost->update(['nginx_config' => $nginxConfig]);
            }

            // Redeploy based on environment
            if ($this->detectionService->isStandalone()) {
                $this->deployToStandalone($virtualHost);
            } elseif ($virtualHost->server_id) {
                $this->deployToKubernetes($virtualHost);
            }

            return [
                'success' => true,
                'virtual_host' => $virtualHost->fresh(),
                'message' => 'Virtual host updated successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Virtual host update failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update virtual host: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete virtual host and clean up resources
     */
    public function delete(VirtualHost $virtualHost): array
    {
        try {
            // Remove based on environment
            if ($this->detectionService->isStandalone()) {
                $this->removeFromStandalone($virtualHost);
            } elseif ($virtualHost->server_id) {
                $this->removeFromKubernetes($virtualHost);
            }

            $virtualHost->delete();

            return [
                'success' => true,
                'message' => 'Virtual host deleted successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Virtual host deletion failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to delete virtual host: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Remove virtual host from standalone NGINX
     */
    protected function removeFromStandalone(VirtualHost $virtualHost): array
    {
        try {
            $hostname = $virtualHost->hostname;
            $username = $this->getSystemUsername($virtualHost);

            // Remove per-user PHP-FPM pool
            $this->standaloneHelper->removePhpFpmPool($username, $virtualHost->php_version);

            // Remove nginx configuration
            $this->standaloneHelper->removeNginxConfig($hostname);

            // Test nginx configuration
            $testResult = $this->standaloneHelper->testNginxConfig();
            if (!$testResult['success']) {
                throw new \Exception('NGINX configuration test failed: ' . $testResult['error']);
            }

            // Reload nginx
            $this->standaloneHelper->reloadSystemdService('nginx');

            Log::info("Virtual host {$hostname} removed from standalone NGINX");

            return [
                'success' => true,
                'message' => 'Virtual host removed from standalone NGINX',
            ];
        } catch (\Exception $e) {
            Log::error('Standalone removal failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to remove from standalone NGINX: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Remove virtual host from Kubernetes
     */
    protected function removeFromKubernetes(VirtualHost $virtualHost): array
    {
        try {
            $kubernetesService = app(KubernetesService::class);
            $name = Str::slug($virtualHost->hostname);
            
            $kubernetesService->deleteResource(
                $virtualHost->server,
                'ingress',
                $name,
                'default'
            );

            return [
                'success' => true,
                'message' => 'Virtual host removed from Kubernetes',
            ];
        } catch (\Exception $e) {
            Log::error('Kubernetes removal failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to remove from Kubernetes: ' . $e->getMessage(),
            ];
        }
    }
}
