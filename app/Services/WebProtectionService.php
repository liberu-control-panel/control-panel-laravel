<?php

namespace App\Services;

use App\Models\HotlinkProtection;
use App\Models\DirectoryProtection;
use App\Models\DirectoryProtectionUser;
use App\Models\CustomErrorPage;
use App\Models\Redirect;
use App\Models\Domain;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WebProtectionService
{
    protected $virtualHostService;

    public function __construct(VirtualHostService $virtualHostService)
    {
        $this->virtualHostService = $virtualHostService;
    }

    /**
     * Setup hotlink protection for a domain
     */
    public function setupHotlinkProtection(Domain $domain, array $data): HotlinkProtection
    {
        $protection = HotlinkProtection::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'enabled' => $data['enabled'] ?? true,
                'allowed_domains' => $data['allowed_domains'] ?? [],
                'protected_extensions' => $data['protected_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'],
                'redirect_url' => $data['redirect_url'] ?? null,
                'allow_blank_referrer' => $data['allow_blank_referrer'] ?? false,
            ]
        );

        // Regenerate NGINX configuration
        $this->virtualHostService->updateVirtualHost($domain);

        return $protection;
    }

    /**
     * Generate NGINX hotlink protection configuration
     */
    public function generateHotlinkProtectionConfig(HotlinkProtection $protection): string
    {
        if (!$protection->enabled) {
            return '';
        }

        $extensions = implode('|', array_map(function($ext) {
            return preg_quote($ext, '/');
        }, $protection->protected_extensions));

        $allowedDomains = $protection->allowed_domains ?: [];
        $domain = $protection->domain->domain_name;
        
        // Add the domain itself to allowed list
        $allowedDomains[] = $domain;
        $allowedDomains[] = "*.{$domain}";

        $validReferers = implode(' ', array_map(function($d) {
            return "server_names {$d}";
        }, $allowedDomains));

        $blankReferer = $protection->allow_blank_referrer ? '' : '~*^$';

        $config = <<<NGINX

    # Hotlink Protection
    location ~* \.({$extensions})$ {
        valid_referers none blocked {$validReferers} {$blankReferer};
        
        if (\$invalid_referer) {
NGINX;

        if ($protection->redirect_url) {
            $config .= "\n            return 302 {$protection->redirect_url};";
        } else {
            $config .= "\n            return 403;";
        }

        $config .= <<<NGINX

        }
    }

NGINX;

        return $config;
    }

    /**
     * Setup directory password protection
     */
    public function setupDirectoryProtection(Domain $domain, string $directoryPath, array $data): DirectoryProtection
    {
        $htpasswdPath = storage_path("app/htpasswd/{$domain->id}/{$directoryPath}/.htpasswd");
        
        // Create directory if it doesn't exist
        $dir = dirname($htpasswdPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $protection = DirectoryProtection::create([
            'domain_id' => $domain->id,
            'directory_path' => $directoryPath,
            'auth_name' => $data['auth_name'] ?? 'Protected Area',
            'htpasswd_file_path' => $htpasswdPath,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Regenerate NGINX configuration
        $this->virtualHostService->updateVirtualHost($domain);

        return $protection;
    }

    /**
     * Add user to directory protection
     */
    public function addDirectoryProtectionUser(DirectoryProtection $protection, string $username, string $password): DirectoryProtectionUser
    {
        // Hash password using APR1-MD5 (Apache compatible)
        $hashedPassword = $this->apr1Hash($password);

        $user = DirectoryProtectionUser::create([
            'directory_protection_id' => $protection->id,
            'username' => $username,
            'password' => $hashedPassword,
        ]);

        // Update .htpasswd file
        $this->updateHtpasswdFile($protection);

        return $user;
    }

    /**
     * Remove user from directory protection
     */
    public function removeDirectoryProtectionUser(DirectoryProtectionUser $user): bool
    {
        $protection = $user->directoryProtection;
        $user->delete();

        // Update .htpasswd file
        $this->updateHtpasswdFile($protection);

        return true;
    }

    /**
     * Update .htpasswd file
     */
    protected function updateHtpasswdFile(DirectoryProtection $protection): void
    {
        $users = $protection->users;
        $content = '';

        foreach ($users as $user) {
            $content .= "{$user->username}:{$user->password}\n";
        }

        file_put_contents($protection->htpasswd_file_path, $content);
        chmod($protection->htpasswd_file_path, 0644);
    }

    /**
     * Generate NGINX directory protection configuration
     */
    public function generateDirectoryProtectionConfig(DirectoryProtection $protection): string
    {
        if (!$protection->is_active) {
            return '';
        }

        $path = $protection->directory_path;
        $authName = $protection->auth_name;
        $htpasswdPath = $protection->htpasswd_file_path;

        return <<<NGINX

    # Directory Protection for {$path}
    location ^~ {$path} {
        auth_basic "{$authName}";
        auth_basic_user_file {$htpasswdPath};
    }

NGINX;
    }

    /**
     * APR1-MD5 password hashing (Apache compatible)
     */
    protected function apr1Hash(string $password): string
    {
        $salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        return crypt($password, '$apr1$' . $salt . '$');
    }

    /**
     * Setup custom error page
     */
    public function setupCustomErrorPage(Domain $domain, int $errorCode, array $data): CustomErrorPage
    {
        $errorPage = CustomErrorPage::updateOrCreate(
            ['domain_id' => $domain->id, 'error_code' => $errorCode],
            [
                'custom_content' => $data['custom_content'] ?? null,
                'custom_file_path' => $data['custom_file_path'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        // If custom content is provided, save it to a file
        if ($errorPage->custom_content && !$errorPage->custom_file_path) {
            $filePath = $this->saveCustomErrorPageFile($domain, $errorCode, $errorPage->custom_content);
            $errorPage->update(['custom_file_path' => $filePath]);
        }

        // Regenerate NGINX configuration
        $this->virtualHostService->updateVirtualHost($domain);

        return $errorPage;
    }

    /**
     * Save custom error page content to file
     */
    protected function saveCustomErrorPageFile(Domain $domain, int $errorCode, string $content): string
    {
        $dir = storage_path("app/error_pages/{$domain->id}");
        
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = "{$dir}/{$errorCode}.html";
        file_put_contents($filePath, $content);

        return $filePath;
    }

    /**
     * Generate NGINX custom error pages configuration
     */
    public function generateCustomErrorPagesConfig(Domain $domain): string
    {
        $errorPages = CustomErrorPage::where('domain_id', $domain->id)
            ->where('is_active', true)
            ->get();

        if ($errorPages->isEmpty()) {
            return '';
        }

        $config = "\n    # Custom Error Pages\n";

        foreach ($errorPages as $page) {
            if ($page->custom_file_path && file_exists($page->custom_file_path)) {
                $config .= "    error_page {$page->error_code} {$page->custom_file_path};\n";
            }
        }

        return $config;
    }

    /**
     * Setup redirect
     */
    public function setupRedirect(Domain $domain, array $data): Redirect
    {
        $redirect = Redirect::create([
            'domain_id' => $domain->id,
            'source_path' => $data['source_path'],
            'destination_url' => $data['destination_url'],
            'redirect_type' => $data['redirect_type'] ?? '301',
            'match_query_string' => $data['match_query_string'] ?? false,
            'is_regex' => $data['is_regex'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 100,
        ]);

        // Regenerate NGINX configuration
        $this->virtualHostService->updateVirtualHost($domain);

        return $redirect;
    }

    /**
     * Generate NGINX redirects configuration
     */
    public function generateRedirectsConfig(Domain $domain): string
    {
        $redirects = Redirect::where('domain_id', $domain->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        if ($redirects->isEmpty()) {
            return '';
        }

        $config = "\n    # Redirects\n";

        foreach ($redirects as $redirect) {
            $source = $redirect->source_path;
            $destination = $redirect->destination_url;
            $type = $redirect->redirect_type;
            
            if ($redirect->is_regex) {
                $config .= "    rewrite {$source} {$destination} permanent;\n";
            } else {
                $locationModifier = $redirect->match_query_string ? '=' : '';
                $config .= "    location {$locationModifier} {$source} {\n";
                $config .= "        return {$type} {$destination};\n";
                $config .= "    }\n";
            }
        }

        return $config;
    }

    /**
     * Delete directory protection
     */
    public function deleteDirectoryProtection(DirectoryProtection $protection): bool
    {
        try {
            // Delete .htpasswd file
            if (file_exists($protection->htpasswd_file_path)) {
                unlink($protection->htpasswd_file_path);
            }

            // Delete protection
            $protection->delete();

            // Regenerate NGINX configuration
            $this->virtualHostService->updateVirtualHost($protection->domain);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete directory protection {$protection->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete custom error page
     */
    public function deleteCustomErrorPage(CustomErrorPage $errorPage): bool
    {
        try {
            // Delete custom file if exists
            if ($errorPage->custom_file_path && file_exists($errorPage->custom_file_path)) {
                unlink($errorPage->custom_file_path);
            }

            $domain = $errorPage->domain;
            $errorPage->delete();

            // Regenerate NGINX configuration
            $this->virtualHostService->updateVirtualHost($domain);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete custom error page {$errorPage->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete redirect
     */
    public function deleteRedirect(Redirect $redirect): bool
    {
        try {
            $domain = $redirect->domain;
            $redirect->delete();

            // Regenerate NGINX configuration
            $this->virtualHostService->updateVirtualHost($domain);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete redirect {$redirect->id}: " . $e->getMessage());
            return false;
        }
    }
}
