<?php

namespace App\Services;

use Exception;
use App\Models\Domain;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class DatabaseService
{
    protected $containerManager;

    public function __construct(ContainerManagerService $containerManager)
    {
        $this->containerManager = $containerManager;
    }

    /**
     * Create a new database for a domain
     */
    public function createDatabase(Domain $domain, array $data): Database
    {
        $engine = $data['engine'] ?? Database::ENGINE_MYSQL;
        $charset = $data['charset'] ?? Database::getDefaultCharset($engine);
        $collation = $data['collation'] ?? Database::getDefaultCollation($engine);

        $database = Database::create([
            'domain_id' => $domain->id,
            'user_id' => $domain->user_id,
            'name' => $data['name'],
            'engine' => $engine,
            'charset' => $charset,
            'collation' => $collation,
            'is_active' => true
        ]);

        // Create the actual database in the container
        $this->createDatabaseInContainer($domain, $database);

        return $database;
    }

    /**
     * Create database in Docker container
     */
    protected function createDatabaseInContainer(Domain $domain, Database $database): bool
    {
        try {
            $containerName = "{$domain->domain_name}_database";

            if ($database->engine === Database::ENGINE_MYSQL) {
                return $this->createMysqlDatabase($containerName, $database);
            } elseif ($database->engine === Database::ENGINE_POSTGRESQL) {
                return $this->createPostgresDatabase($containerName, $database);
            }

            return false;
        } catch (Exception $e) {
            Log::error("Failed to create database {$database->name} in container: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create MySQL database
     */
    protected function createMysqlDatabase(string $containerName, Database $database): bool
    {
        $commands = [
            "CREATE DATABASE IF NOT EXISTS `{$database->name}` CHARACTER SET {$database->charset} COLLATE {$database->collation};",
            "FLUSH PRIVILEGES;"
        ];

        foreach ($commands as $command) {
            $process = new Process([
                'docker', 'exec', $containerName, 
                'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'), 
                '-e', $command
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error("MySQL command failed: " . $process->getErrorOutput());
                return false;
            }
        }

        return true;
    }

    /**
     * Create PostgreSQL database
     */
    protected function createPostgresDatabase(string $containerName, Database $database): bool
    {
        $process = new Process([
            'docker', 'exec', $containerName,
            'createdb', '-U', 'postgres', $database->name
        ]);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Create database user
     */
    public function createDatabaseUser(Database $database, array $data): DatabaseUser
    {
        $username = $data['username'];
        $password = $data['password'] ?? Str::random(16);
        $host = $data['host'] ?? '%';
        $privileges = $data['privileges'] ?? [DatabaseUser::PRIVILEGE_ALL];

        $databaseUser = DatabaseUser::create([
            'database_id' => $database->id,
            'username' => $username,
            'password' => Hash::make($password),
            'host' => $host,
            'privileges' => $privileges,
            'is_active' => true
        ]);

        // Create the actual user in the container
        $this->createUserInContainer($database, $databaseUser, $password);

        return $databaseUser;
    }

    /**
     * Create database user in container
     */
    protected function createUserInContainer(Database $database, DatabaseUser $databaseUser, string $password): bool
    {
        try {
            $domain = $database->domain;
            $containerName = "{$domain->domain_name}_database";

            if ($database->engine === Database::ENGINE_MYSQL) {
                return $this->createMysqlUser($containerName, $database, $databaseUser, $password);
            } elseif ($database->engine === Database::ENGINE_POSTGRESQL) {
                return $this->createPostgresUser($containerName, $database, $databaseUser, $password);
            }

            return false;
        } catch (Exception $e) {
            Log::error("Failed to create database user {$databaseUser->username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create MySQL user
     */
    protected function createMysqlUser(string $containerName, Database $database, DatabaseUser $databaseUser, string $password): bool
    {
        $privileges = $this->formatMysqlPrivileges($databaseUser->privileges);

        $commands = [
            "CREATE USER IF NOT EXISTS '{$databaseUser->username}'@'{$databaseUser->host}' IDENTIFIED BY '{$password}';",
            "GRANT {$privileges} ON `{$database->name}`.* TO '{$databaseUser->username}'@'{$databaseUser->host}';",
            "FLUSH PRIVILEGES;"
        ];

        foreach ($commands as $command) {
            $process = new Process([
                'docker', 'exec', $containerName,
                'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                '-e', $command
            ]);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error("MySQL user creation failed: " . $process->getErrorOutput());
                return false;
            }
        }

        return true;
    }

    /**
     * Create PostgreSQL user
     */
    protected function createPostgresUser(string $containerName, Database $database, DatabaseUser $databaseUser, string $password): bool
    {
        // Create user
        $createUserProcess = new Process([
            'docker', 'exec', $containerName,
            'createuser', '-U', 'postgres', $databaseUser->username
        ]);
        $createUserProcess->run();

        // Set password
        $setPasswordProcess = new Process([
            'docker', 'exec', $containerName,
            'psql', '-U', 'postgres', '-c',
            "ALTER USER {$databaseUser->username} WITH PASSWORD '{$password}';"
        ]);
        $setPasswordProcess->run();

        // Grant privileges
        $privileges = $this->formatPostgresPrivileges($databaseUser->privileges);
        $grantProcess = new Process([
            'docker', 'exec', $containerName,
            'psql', '-U', 'postgres', '-c',
            "GRANT {$privileges} ON DATABASE {$database->name} TO {$databaseUser->username};"
        ]);
        $grantProcess->run();

        return $createUserProcess->isSuccessful() && 
               $setPasswordProcess->isSuccessful() && 
               $grantProcess->isSuccessful();
    }

    /**
     * Format MySQL privileges
     */
    protected function formatMysqlPrivileges(array $privileges): string
    {
        if (in_array(DatabaseUser::PRIVILEGE_ALL, $privileges)) {
            return 'ALL PRIVILEGES';
        }

        return implode(', ', $privileges);
    }

    /**
     * Format PostgreSQL privileges
     */
    protected function formatPostgresPrivileges(array $privileges): string
    {
        if (in_array(DatabaseUser::PRIVILEGE_ALL, $privileges)) {
            return 'ALL PRIVILEGES';
        }

        $postgresPrivileges = [];
        foreach ($privileges as $privilege) {
            $postgresPrivileges[] = match($privilege) {
                DatabaseUser::PRIVILEGE_SELECT => 'SELECT',
                DatabaseUser::PRIVILEGE_INSERT => 'INSERT',
                DatabaseUser::PRIVILEGE_UPDATE => 'UPDATE',
                DatabaseUser::PRIVILEGE_DELETE => 'DELETE',
                DatabaseUser::PRIVILEGE_CREATE => 'CREATE',
                DatabaseUser::PRIVILEGE_DROP => 'DROP',
                default => $privilege
            };
        }

        return implode(', ', $postgresPrivileges);
    }

    /**
     * Delete database
     */
    public function deleteDatabase(Database $database): bool
    {
        try {
            $domain = $database->domain;
            $containerName = "{$domain->domain_name}_database";

            // Delete from container
            if ($database->engine === Database::ENGINE_MYSQL) {
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                    '-e', "DROP DATABASE IF EXISTS `{$database->name}`;"
                ]);
            } else {
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'dropdb', '-U', 'postgres', $database->name
                ]);
            }

            $process->run();

            if ($process->isSuccessful()) {
                // Delete database users
                $database->databaseUsers()->delete();

                // Delete database record
                $database->delete();

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error("Failed to delete database {$database->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database size
     */
    public function getDatabaseSize(Database $database): int
    {
        try {
            $domain = $database->domain;
            $containerName = "{$domain->domain_name}_database";

            if ($database->engine === Database::ENGINE_MYSQL) {
                $query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='{$database->name}';";
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                    '-e', $query, '--skip-column-names'
                ]);
            } else {
                $query = "SELECT pg_size_pretty(pg_database_size('{$database->name}'));";
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'psql', '-U', 'postgres', '-t', '-c', $query
                ]);
            }

            $process->run();

            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());

                // Convert to bytes
                if ($database->engine === Database::ENGINE_MYSQL) {
                    return (int) (floatval($output) * 1024 * 1024); // MB to bytes
                } else {
                    // Parse PostgreSQL output (e.g., "8192 bytes", "1024 kB", "1 MB")
                    if (preg_match('/(\d+(?:\.\d+)?)\s*(\w+)/', $output, $matches)) {
                        $size = floatval($matches[1]);
                        $unit = strtolower($matches[2]);

                        return match($unit) {
                            'bytes' => (int) $size,
                            'kb' => (int) ($size * 1024),
                            'mb' => (int) ($size * 1024 * 1024),
                            'gb' => (int) ($size * 1024 * 1024 * 1024),
                            default => 0
                        };
                    }
                }
            }

            return 0;
        } catch (Exception $e) {
            Log::error("Failed to get database size for {$database->name}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create database backup
     */
    public function createBackup(Database $database, string $backupPath): bool
    {
        try {
            $domain = $database->domain;
            $containerName = "{$domain->domain_name}_database";
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "{$database->name}_{$timestamp}.sql";

            if ($database->engine === Database::ENGINE_MYSQL) {
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'mysqldump', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                    '--single-transaction', '--routines', '--triggers',
                    $database->name
                ]);
            } else {
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'pg_dump', '-U', 'postgres', $database->name
                ]);
            }

            $process->run();

            if ($process->isSuccessful()) {
                $backupContent = $process->getOutput();
                file_put_contents($backupPath . '/' . $filename, $backupContent);

                // Update database size
                $database->update(['size' => $this->getDatabaseSize($database)]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error("Failed to create backup for database {$database->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(Database $database, string $backupFile): bool
    {
        try {
            $domain = $database->domain;
            $containerName = "{$domain->domain_name}_database";

            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found: {$backupFile}");
            }

            // Copy backup file to container
            $containerBackupPath = "/tmp/" . basename($backupFile);
            $copyProcess = new Process([
                'docker', 'cp', $backupFile, "{$containerName}:{$containerBackupPath}"
            ]);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                throw new Exception("Failed to copy backup file to container");
            }

            // Restore database
            if ($database->engine === Database::ENGINE_MYSQL) {
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                    $database->name, '<', $containerBackupPath
                ]);
            } else {
                $process = new Process([
                    'docker', 'exec', $containerName,
                    'psql', '-U', 'postgres', '-d', $database->name, '-f', $containerBackupPath
                ]);
            }

            $process->run();

            // Clean up
            $cleanupProcess = new Process([
                'docker', 'exec', $containerName, 'rm', $containerBackupPath
            ]);
            $cleanupProcess->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            Log::error("Failed to restore backup for database {$database->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(Database $database): array
    {
        try {
            $domain = $database->domain;
            $containerName = "{$domain->domain_name}_database";

            $stats = [
                'size' => $this->getDatabaseSize($database),
                'table_count' => 0,
                'connection_count' => 0
            ];

            if ($database->engine === Database::ENGINE_MYSQL) {
                // Get table count
                $tableCountProcess = new Process([
                    'docker', 'exec', $containerName,
                    'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                    '-e', "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$database->name}';",
                    '--skip-column-names'
                ]);
                $tableCountProcess->run();

                if ($tableCountProcess->isSuccessful()) {
                    $stats['table_count'] = (int) trim($tableCountProcess->getOutput());
                }

                // Get connection count
                $connectionProcess = new Process([
                    'docker', 'exec', $containerName,
                    'mysql', '-u', 'root', '-p' . env('DB_ROOT_PASSWORD', 'root'),
                    '-e', "SHOW STATUS LIKE 'Threads_connected';",
                    '--skip-column-names'
                ]);
                $connectionProcess->run();

                if ($connectionProcess->isSuccessful()) {
                    $output = trim($connectionProcess->getOutput());
                    if (preg_match('/\s+(\d+)$/', $output, $matches)) {
                        $stats['connection_count'] = (int) $matches[1];
                    }
                }
            }

            return $stats;
        } catch (Exception $e) {
            Log::error("Failed to get database stats for {$database->name}: " . $e->getMessage());
            return [
                'size' => 0,
                'table_count' => 0,
                'connection_count' => 0
            ];
        }
    }
}