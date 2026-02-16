<?php

namespace App\Services\ManagedDatabase;

use App\Models\Database;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Managed Database Manager
 * Coordinates all managed database providers
 */
class ManagedDatabaseManager
{
    protected array $providers = [];

    public function __construct()
    {
        $this->registerProviders();
    }

    /**
     * Register all managed database providers
     */
    protected function registerProviders(): void
    {
        $this->providers = [
            Database::PROVIDER_AWS => app(AwsRdsProvider::class),
            Database::PROVIDER_AZURE => app(AzureDatabaseProvider::class),
            Database::PROVIDER_DIGITALOCEAN => app(DigitalOceanDatabaseProvider::class),
            Database::PROVIDER_OVH => app(OvhDatabaseProvider::class),
            Database::PROVIDER_GCP => app(GoogleCloudSqlProvider::class),
        ];
    }

    /**
     * Get provider for a database
     */
    public function getProvider(Database $database): ?ManagedDatabaseProviderInterface
    {
        if (!$database->isManaged() || !$database->provider) {
            return null;
        }

        return $this->providers[$database->provider] ?? null;
    }

    /**
     * Get provider by name
     */
    public function getProviderByName(string $providerName): ?ManagedDatabaseProviderInterface
    {
        return $this->providers[$providerName] ?? null;
    }

    /**
     * Get all providers
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Test connection to managed database
     */
    public function testConnection(Database $database): bool
    {
        try {
            $provider = $this->getProvider($database);
            
            if (!$provider) {
                Log::warning("No provider found for database", ['database_id' => $database->id]);
                return false;
            }

            return $provider->testConnection($database);
        } catch (Exception $e) {
            Log::error("Failed to test managed database connection", [
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a new managed database
     */
    public function createDatabase(string $providerName, array $config): array
    {
        $provider = $this->getProviderByName($providerName);

        if (!$provider) {
            throw new Exception("Provider not found: {$providerName}");
        }

        return $provider->createDatabase($config);
    }

    /**
     * Delete a managed database
     */
    public function deleteDatabase(Database $database): bool
    {
        $provider = $this->getProvider($database);

        if (!$provider) {
            Log::warning("No provider found for database deletion", ['database_id' => $database->id]);
            return false;
        }

        return $provider->deleteDatabase($database);
    }

    /**
     * Get database metrics
     */
    public function getMetrics(Database $database): array
    {
        $provider = $this->getProvider($database);

        if (!$provider) {
            return [];
        }

        return $provider->getMetrics($database);
    }

    /**
     * Scale database instance
     */
    public function scaleInstance(Database $database, array $config): bool
    {
        $provider = $this->getProvider($database);

        if (!$provider) {
            return false;
        }

        return $provider->scaleInstance($database, $config);
    }

    /**
     * Create database backup
     */
    public function createBackup(Database $database, string $backupName): bool
    {
        $provider = $this->getProvider($database);

        if (!$provider) {
            return false;
        }

        return $provider->createBackup($database, $backupName);
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(Database $database, string $backupIdentifier): bool
    {
        $provider = $this->getProvider($database);

        if (!$provider) {
            return false;
        }

        return $provider->restoreBackup($database, $backupIdentifier);
    }

    /**
     * Get available instance types for a provider
     */
    public function getAvailableInstanceTypes(string $providerName): array
    {
        $provider = $this->getProviderByName($providerName);

        if (!$provider) {
            return [];
        }

        return $provider->getAvailableInstanceTypes();
    }

    /**
     * Get available regions for a provider
     */
    public function getAvailableRegions(string $providerName): array
    {
        $provider = $this->getProviderByName($providerName);

        if (!$provider) {
            return [];
        }

        return $provider->getAvailableRegions();
    }

    /**
     * Check if a provider is supported
     */
    public function isProviderSupported(string $providerName): bool
    {
        return isset($this->providers[$providerName]);
    }
}
