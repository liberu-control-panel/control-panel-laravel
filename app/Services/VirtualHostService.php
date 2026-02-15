<?php

namespace App\Services;

use App\Models\VirtualHost;
use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VirtualHostService
{
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

            // Deploy to Kubernetes/NGINX Ingress if server is configured
            if ($virtualHost->server_id) {
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
     * Generate nginx configuration for virtual host
     */
    protected function generateNginxConfig(VirtualHost $virtualHost): string
    {
        $hostname = $virtualHost->hostname;
        $documentRoot = $virtualHost->document_root;
        $phpVersion = str_replace('.', '-', $virtualHost->php_version);

        $config = "server {\n";
        $config .= "    listen 80;\n";
        
        if ($virtualHost->ssl_enabled) {
            $config .= "    listen 443 ssl http2;\n";
            $config .= "    ssl_certificate /etc/letsencrypt/live/{$hostname}/fullchain.pem;\n";
            $config .= "    ssl_certificate_key /etc/letsencrypt/live/{$hostname}/privkey.pem;\n";
        }
        
        $config .= "    server_name {$hostname};\n";
        $config .= "    root {$documentRoot};\n";
        $config .= "    index index.php index.html index.htm;\n\n";
        
        $config .= "    location / {\n";
        $config .= "        try_files \$uri \$uri/ /index.php?\$query_string;\n";
        $config .= "    }\n\n";
        
        $config .= "    location ~ \\.php$ {\n";
        $config .= "        fastcgi_pass php-versions-{$phpVersion}:9000;\n";
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
            // Let's Encrypt certificate is automatically requested by cert-manager
            // via the Kubernetes Ingress annotations
            
            Log::info("Let's Encrypt certificate requested for {$virtualHost->hostname}");
            
            return [
                'success' => true,
                'message' => 'Let\'s Encrypt certificate request submitted',
            ];
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

            // Redeploy to Kubernetes if needed
            if ($virtualHost->server_id) {
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
            // Remove from Kubernetes
            if ($virtualHost->server_id) {
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
