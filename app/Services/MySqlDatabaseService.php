<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MySqlDatabaseService
{
    public function createDatabase(string $name, string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): bool
    {
        try {
            DB::statement("CREATE DATABASE `$name` CHARACTER SET $charset COLLATE $collation");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create database: " . $e->getMessage());
            return false;
        }
    }

    public function modifyDatabase(string $name, string $charset, string $collation): bool
    {
        try {
            DB::statement("ALTER DATABASE `$name` CHARACTER SET $charset COLLATE $collation");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to modify database: " . $e->getMessage());
            return false;
        }
    }

    public function dropDatabase(string $name): bool
    {
        try {
            DB::statement("DROP DATABASE `$name`");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to drop database: " . $e->getMessage());
            return false;
        }
    }
}