<?php

namespace App\Services\ManagedDatabase;

use App\Models\Database;

interface ManagedDatabaseProviderInterface
{
    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Test connection to managed database
     */
    public function testConnection(Database $database): bool;

    /**
     * Create a new managed database instance
     */
    public function createDatabase(array $config): array;

    /**
     * Delete a managed database instance
     */
    public function deleteDatabase(Database $database): bool;

    /**
     * Get database connection details
     */
    public function getConnectionDetails(Database $database): array;

    /**
     * Check if database instance exists
     */
    public function databaseExists(string $instanceIdentifier): bool;

    /**
     * Get database metrics
     */
    public function getMetrics(Database $database): array;

    /**
     * Scale database instance
     */
    public function scaleInstance(Database $database, array $config): bool;

    /**
     * Create database backup
     */
    public function createBackup(Database $database, string $backupName): bool;

    /**
     * Restore database from backup
     */
    public function restoreBackup(Database $database, string $backupIdentifier): bool;

    /**
     * List available instance types/sizes
     */
    public function getAvailableInstanceTypes(): array;

    /**
     * List available regions
     */
    public function getAvailableRegions(): array;
}
