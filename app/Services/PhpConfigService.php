<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\PhpConfig;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PhpConfigService
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
     * Get or create the PHP configuration for a domain.
     */
    public function getOrCreate(Domain $domain): PhpConfig
    {
        return PhpConfig::firstOrCreate(
            ['domain_id' => $domain->id],
            ['php_version' => '8.2']
        );
    }

    /**
     * Update the PHP configuration for a domain and deploy it.
     */
    public function update(Domain $domain, array $settings): PhpConfig
    {
        $config = $this->getOrCreate($domain);
        $config->update($settings);

        $this->deploy($domain, $config);

        return $config->fresh();
    }

    /**
     * Deploy the PHP configuration to disk / container.
     */
    public function deploy(Domain $domain, PhpConfig $config): bool
    {
        try {
            $iniContent = $config->toIniString();

            if ($this->detectionService->isStandalone()) {
                return $this->deployStandalone($domain, $config, $iniContent);
            }

            return $this->deployContainer($domain, $config, $iniContent);
        } catch (Exception $e) {
            Log::error("Failed to deploy PHP config for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deploy php.ini overrides to the standalone (bare-metal / VM) system.
     *
     * Places a conf file in the PHP-FPM per-domain pool drop-in directory so
     * that the global php.ini is not touched and each virtual host is isolated.
     */
    protected function deployStandalone(Domain $domain, PhpConfig $config, string $iniContent): bool
    {
        $phpVersion = $config->php_version;
        $confDir    = "/etc/php/{$phpVersion}/fpm/conf.d";
        $confFile   = "{$confDir}/99-{$domain->domain_name}.ini";

        if (!is_dir($confDir)) {
            Log::warning("PHP {$phpVersion} FPM conf.d directory not found: {$confDir}");
            return false;
        }

        file_put_contents($confFile, $iniContent);

        // Reload PHP-FPM for the changes to take effect
        $process = new Process(['systemctl', 'reload', "php{$phpVersion}-fpm"]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning("Could not reload php{$phpVersion}-fpm: " . $process->getErrorOutput());
        }

        return true;
    }

    /**
     * Deploy php.ini overrides inside a Docker container.
     */
    protected function deployContainer(Domain $domain, PhpConfig $config, string $iniContent): bool
    {
        $phpVersion    = $config->php_version;
        $containerName = "{$domain->domain_name}_php";
        $confPath      = "/etc/php/{$phpVersion}/fpm/conf.d/99-domain.ini";

        $process = new Process([
            'docker', 'exec', $containerName,
            'bash', '-c', "cat > {$confPath}",
        ]);
        $process->setInput($iniContent);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error("Failed to write PHP config to container {$containerName}: " . $process->getErrorOutput());
            return false;
        }

        // Reload PHP-FPM inside the container
        $reload = new Process(['docker', 'exec', $containerName, 'kill', '-USR2', '1']);
        $reload->run();

        return true;
    }

    /**
     * Remove PHP configuration overrides for a domain (e.g. when it is deleted).
     */
    public function remove(Domain $domain): bool
    {
        try {
            $config = $domain->phpConfig;
            if (!$config) {
                return true;
            }

            if ($this->detectionService->isStandalone()) {
                $phpVersion = $config->php_version;
                $confFile   = "/etc/php/{$phpVersion}/fpm/conf.d/99-{$domain->domain_name}.ini";
                if (file_exists($confFile)) {
                    unlink($confFile);
                }

                $process = new Process(['systemctl', 'reload', "php{$phpVersion}-fpm"]);
                $process->run();
            }

            $config->delete();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to remove PHP config for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }
}
