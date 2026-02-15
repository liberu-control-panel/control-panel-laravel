<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Database;
use App\Models\WordPressApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class WordPressService
{
    protected SshConnectionService $sshService;
    
    const WORDPRESS_DOWNLOAD_URL = 'https://wordpress.org/latest.tar.gz';
    const WORDPRESS_API_URL = 'https://api.wordpress.org/core/version-check/1.7/';

    public function __construct(SshConnectionService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Get the latest WordPress version
     */
    public function getLatestVersion(): ?string
    {
        try {
            $response = Http::timeout(10)->get(self::WORDPRESS_API_URL);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['offers'][0]['version'] ?? null;
            }
        } catch (Exception $e) {
            Log::error("Failed to get WordPress version: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Install WordPress on a domain
     */
    public function installWordPress(WordPressApplication $wp): bool
    {
        try {
            $domain = $wp->domain;
            $server = $domain->server;

            if (!$server) {
                throw new Exception("No server assigned to domain {$domain->domain_name}");
            }

            // Update status
            $wp->update(['status' => 'installing', 'installation_log' => 'Starting installation...']);

            // Get latest WordPress version if not set
            if (!$wp->version) {
                $latestVersion = $this->getLatestVersion();
                $wp->update(['version' => $latestVersion]);
            }

            // Determine installation path
            $installPath = $this->getInstallationPath($domain, $wp);
            
            // Execute installation steps
            $log = $this->executeInstallation($server, $domain, $wp, $installPath);

            // Update status to installed
            $wp->update([
                'status' => 'installed',
                'installation_log' => $log,
                'installed_at' => now(),
            ]);

            Log::info("WordPress installed successfully for domain {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("WordPress installation failed: " . $e->getMessage());
            
            $wp->update([
                'status' => 'failed',
                'installation_log' => $wp->installation_log . "\n\nError: " . $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Execute the WordPress installation
     */
    protected function executeInstallation($server, Domain $domain, WordPressApplication $wp, string $installPath): string
    {
        $log = [];

        // Connect to server
        $connection = $this->sshService->connect($server);
        
        if (!$connection) {
            throw new Exception("Failed to connect to server");
        }

        try {
            // Create installation directory
            $log[] = "Creating installation directory: {$installPath}";
            $this->sshService->executeCommand($connection, "mkdir -p {$installPath}");

            // Download WordPress
            $log[] = "Downloading WordPress...";
            $tmpPath = "/tmp/wordpress-" . Str::random(10);
            $this->sshService->executeCommand($connection, "mkdir -p {$tmpPath}");
            $this->sshService->executeCommand($connection, "cd {$tmpPath} && wget -q " . self::WORDPRESS_DOWNLOAD_URL);
            $this->sshService->executeCommand($connection, "cd {$tmpPath} && tar -xzf latest.tar.gz");
            
            // Move WordPress files
            $log[] = "Installing WordPress files...";
            $this->sshService->executeCommand($connection, "cp -r {$tmpPath}/wordpress/* {$installPath}/");
            $this->sshService->executeCommand($connection, "rm -rf {$tmpPath}");

            // Set permissions
            $log[] = "Setting permissions...";
            $this->sshService->executeCommand($connection, "chmod -R 755 {$installPath}");
            $this->sshService->executeCommand($connection, "chmod -R 775 {$installPath}/wp-content");

            // Create wp-config.php
            $log[] = "Configuring WordPress...";
            $this->createWpConfig($connection, $installPath, $wp);

            // Install WordPress via WP-CLI if available
            if ($this->hasWpCli($connection)) {
                $log[] = "Installing WordPress via WP-CLI...";
                $this->installViaWpCli($connection, $installPath, $wp);
            } else {
                $log[] = "WP-CLI not available, manual setup required via web interface";
            }

            return implode("\n", $log);

        } finally {
            $this->sshService->disconnect($connection);
        }
    }

    /**
     * Create wp-config.php file
     */
    protected function createWpConfig($connection, string $installPath, WordPressApplication $wp): void
    {
        $database = $wp->database;
        
        if (!$database) {
            throw new Exception("No database assigned to WordPress application");
        }

        $config = $this->generateWpConfig(
            $database->database_name,
            $database->username,
            $database->password,
            $database->host ?? 'localhost',
            $wp->site_url
        );

        // Create wp-config.php
        $configPath = "{$installPath}/wp-config.php";
        $this->sshService->executeCommand(
            $connection,
            "cat > {$configPath} << 'WPCONFIG'\n{$config}\nWPCONFIG"
        );
    }

    /**
     * Generate wp-config.php content
     */
    protected function generateWpConfig(string $dbName, string $dbUser, string $dbPassword, string $dbHost, string $siteUrl): string
    {
        $authKey = Str::random(64);
        $secureAuthKey = Str::random(64);
        $loggedInKey = Str::random(64);
        $nonceKey = Str::random(64);
        $authSalt = Str::random(64);
        $secureAuthSalt = Str::random(64);
        $loggedInSalt = Str::random(64);
        $nonceSalt = Str::random(64);

        return <<<PHP
<?php
/**
 * WordPress Configuration File
 * Generated by Liberu Control Panel
 */

// Database Settings
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASSWORD', '{$dbPassword}');
define('DB_HOST', '{$dbHost}');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Authentication Unique Keys and Salts
define('AUTH_KEY',         '{$authKey}');
define('SECURE_AUTH_KEY',  '{$secureAuthKey}');
define('LOGGED_IN_KEY',    '{$loggedInKey}');
define('NONCE_KEY',        '{$nonceKey}');
define('AUTH_SALT',        '{$authSalt}');
define('SECURE_AUTH_SALT', '{$secureAuthSalt}');
define('LOGGED_IN_SALT',   '{$loggedInSalt}');
define('NONCE_SALT',       '{$nonceSalt}');

// WordPress Database Table prefix
\$table_prefix = 'wp_';

// WordPress Debugging Mode
define('WP_DEBUG', false);

// Site URL
define('WP_HOME', '{$siteUrl}');
define('WP_SITEURL', '{$siteUrl}');

// Absolute path to the WordPress directory
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Sets up WordPress vars and included files
require_once ABSPATH . 'wp-settings.php';
PHP;
    }

    /**
     * Check if WP-CLI is available
     */
    protected function hasWpCli($connection): bool
    {
        $result = $this->sshService->executeCommand($connection, "which wp");
        return !empty(trim($result));
    }

    /**
     * Install WordPress via WP-CLI
     */
    protected function installViaWpCli($connection, string $installPath, WordPressApplication $wp): void
    {
        $domain = $wp->domain;
        
        $command = sprintf(
            "cd %s && wp core install --url='%s' --title='%s' --admin_user='%s' --admin_email='%s' --admin_password='%s' --skip-email",
            escapeshellarg($installPath),
            escapeshellarg($wp->site_url),
            escapeshellarg($wp->site_title),
            escapeshellarg($wp->admin_username),
            escapeshellarg($wp->admin_email),
            escapeshellarg($wp->admin_password)
        );

        $this->sshService->executeCommand($connection, $command);
    }

    /**
     * Get the installation path for WordPress
     */
    protected function getInstallationPath(Domain $domain, WordPressApplication $wp): string
    {
        // If running in container, use container path
        $container = $domain->getWebContainer();
        
        if ($container) {
            return "/var/www/{$domain->domain_name}" . $wp->install_path;
        }

        // Otherwise use direct server path
        return "/var/www/{$domain->domain_name}" . $wp->install_path;
    }

    /**
     * Update WordPress to latest version
     */
    public function updateWordPress(WordPressApplication $wp): bool
    {
        try {
            $domain = $wp->domain;
            $server = $domain->server;

            if (!$server) {
                throw new Exception("No server assigned to domain");
            }

            $wp->update(['status' => 'updating']);

            $connection = $this->sshService->connect($server);
            
            if (!$connection) {
                throw new Exception("Failed to connect to server");
            }

            try {
                $installPath = $this->getInstallationPath($domain, $wp);

                if ($this->hasWpCli($connection)) {
                    // Update via WP-CLI
                    $this->sshService->executeCommand(
                        $connection,
                        "cd {$installPath} && wp core update"
                    );

                    // Get new version
                    $version = trim($this->sshService->executeCommand(
                        $connection,
                        "cd {$installPath} && wp core version"
                    ));

                    $wp->update([
                        'status' => 'installed',
                        'version' => $version,
                        'last_update_check' => now(),
                    ]);
                } else {
                    throw new Exception("WP-CLI not available for automatic updates");
                }

                Log::info("WordPress updated successfully for domain {$domain->domain_name}");
                return true;

            } finally {
                $this->sshService->disconnect($connection);
            }

        } catch (Exception $e) {
            Log::error("WordPress update failed: " . $e->getMessage());
            
            $wp->update(['status' => 'installed']); // Revert to installed status
            
            return false;
        }
    }

    /**
     * Check for WordPress updates
     */
    public function checkForUpdates(WordPressApplication $wp): ?string
    {
        $latestVersion = $this->getLatestVersion();
        $currentVersion = $wp->version;

        $wp->update(['last_update_check' => now()]);

        if ($latestVersion && version_compare($currentVersion, $latestVersion, '<')) {
            return $latestVersion;
        }

        return null;
    }
}
