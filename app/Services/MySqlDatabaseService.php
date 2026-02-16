<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MySqlDatabaseService
{
    protected StandaloneServiceHelper $standaloneHelper;
    protected DeploymentDetectionService $detectionService;

    public function __construct(
        StandaloneServiceHelper $standaloneHelper,
        DeploymentDetectionService $detectionService
    ) {
        $this->standaloneHelper = $standaloneHelper;
        $this->detectionService = $detectionService;
    }

    public function createDatabase(string $name, string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): array
    {
        try {
            if ($this->detectionService->isStandalone()) {
                $success = $this->standaloneHelper->createMysqlDatabase($name, $charset, $collation);
                return [
                    'success' => $success,
                    'message' => $success ? 'Database created successfully' : 'Failed to create database'
                ];
            }

            DB::statement("CREATE DATABASE `$name` CHARACTER SET $charset COLLATE $collation");
            return [
                'success' => true,
                'message' => 'Database created successfully'
            ];
        } catch (Exception $e) {
            Log::error("Failed to create database: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function dropDatabase(string $name): array
    {
        try {
            if ($this->detectionService->isStandalone()) {
                $success = $this->standaloneHelper->dropMysqlDatabase($name);
                return [
                    'success' => $success,
                    'message' => $success ? 'Database dropped successfully' : 'Failed to drop database'
                ];
            }

            DB::statement("DROP DATABASE `$name`");
            return [
                'success' => true,
                'message' => 'Database dropped successfully'
            ];
        } catch (Exception $e) {
            Log::error("Failed to drop database: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function createUser(string $username, string $password, string $host = 'localhost'): array
    {
        try {
            if ($this->detectionService->isStandalone()) {
                $command = "CREATE USER IF NOT EXISTS '{$username}'@'{$host}' IDENTIFIED BY '{$password}';";
                $result = $this->standaloneHelper->executeMysqlCommand($command);
                return [
                    'success' => $result['success'],
                    'message' => $result['success'] ? 'User created successfully' : 'Failed to create user'
                ];
            }

            DB::statement("CREATE USER IF NOT EXISTS '{$username}'@'{$host}' IDENTIFIED BY '{$password}'");
            return [
                'success' => true,
                'message' => 'User created successfully'
            ];
        } catch (Exception $e) {
            Log::error("Failed to create user: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function grantPrivileges(string $username, string $database, array $privileges, string $host = 'localhost'): array
    {
        try {
            $privilegeString = implode(', ', $privileges);
            $command = "GRANT {$privilegeString} ON `{$database}`.* TO '{$username}'@'{$host}'; FLUSH PRIVILEGES;";

            if ($this->detectionService->isStandalone()) {
                $result = $this->standaloneHelper->executeMysqlCommand($command);
                return [
                    'success' => $result['success'],
                    'message' => $result['success'] ? 'Privileges granted successfully' : 'Failed to grant privileges'
                ];
            }

            DB::statement($command);
            return [
                'success' => true,
                'message' => 'Privileges granted successfully'
            ];
        } catch (Exception $e) {
            Log::error("Failed to grant privileges: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}