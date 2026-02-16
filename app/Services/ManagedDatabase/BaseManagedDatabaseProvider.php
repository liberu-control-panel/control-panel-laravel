<?php

namespace App\Services\ManagedDatabase;

use App\Models\Database;
use Illuminate\Support\Facades\Log;
use Exception;

abstract class BaseManagedDatabaseProvider implements ManagedDatabaseProviderInterface
{
    /**
     * Test connection to managed database
     */
    public function testConnection(Database $database): bool
    {
        try {
            $config = [
                'driver' => $this->mapEngineToDriver($database->engine),
                'host' => $database->external_host,
                'port' => $database->external_port,
                'database' => $database->name,
                'username' => $database->external_username,
                'password' => $database->external_password,
            ];

            // Add SSL configuration if enabled
            if ($database->use_ssl) {
                $config['options'] = [
                    \PDO::MYSQL_ATTR_SSL_CA => $database->ssl_ca ?? null,
                    \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
                ];
            }

            // Temporarily configure connection
            config(["database.connections.temp_test" => $config]);

            // Test the connection
            $pdo = \DB::connection('temp_test')->getPdo();

            return $pdo !== null;
        } catch (Exception $e) {
            Log::error("Failed to test connection to managed database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database connection details
     */
    public function getConnectionDetails(Database $database): array
    {
        return [
            'host' => $database->external_host,
            'port' => $database->external_port,
            'database' => $database->name,
            'username' => $database->external_username,
            'password' => $database->external_password,
            'engine' => $database->engine,
            'ssl_enabled' => $database->use_ssl,
            'region' => $database->region,
        ];
    }

    /**
     * Map database engine to PDO driver
     */
    protected function mapEngineToDriver(string $engine): string
    {
        return match($engine) {
            Database::ENGINE_MYSQL, Database::ENGINE_MARIADB => 'mysql',
            Database::ENGINE_POSTGRESQL => 'pgsql',
            Database::ENGINE_REDIS => 'redis',
            default => 'mysql'
        };
    }

    /**
     * Validate configuration
     */
    protected function validateConfig(array $config, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    /**
     * Get API credentials
     */
    protected function getCredentials(): array
    {
        $provider = $this->getName();
        
        return [
            'access_key' => config("managed-databases.{$provider}.access_key"),
            'secret_key' => config("managed-databases.{$provider}.secret_key"),
            'api_token' => config("managed-databases.{$provider}.api_token"),
            'region' => config("managed-databases.{$provider}.default_region"),
        ];
    }

    /**
     * Log activity
     */
    protected function logActivity(string $action, Database $database, array $context = []): void
    {
        Log::info("Managed Database {$action}", array_merge([
            'provider' => $this->getName(),
            'database_id' => $database->id,
            'database_name' => $database->name,
            'instance_identifier' => $database->instance_identifier,
        ], $context));
    }

    /**
     * Log error
     */
    protected function logError(string $action, Exception $e, array $context = []): void
    {
        Log::error("Managed Database {$action} failed", array_merge([
            'provider' => $this->getName(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], $context));
    }
}
