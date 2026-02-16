<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| These routes are used by Kubernetes, Docker, and load balancers to
| monitor the health and readiness of the application.
|
*/

// Basic health check - always returns 200 if PHP is running
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

// Liveness probe - checks if the application is alive
// Returns 200 if alive, 500 if there's a critical error
Route::get('/health/live', function () {
    try {
        // Check if we can execute PHP code
        $check = 'ok';
        
        return response()->json([
            'status' => 'alive',
            'checks' => [
                'php' => $check === 'ok',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'dead',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
})->name('health.live');

// Readiness probe - checks if the application is ready to serve traffic
// Returns 200 if ready, 503 if not ready
Route::get('/health/ready', function () {
    $errors = [];
    $checks = [];

    try {
        // Check database connection
        try {
            DB::connection()->getPdo();
            DB::connection()->getDatabaseName();
            $checks['database'] = true;
        } catch (\Throwable $e) {
            $checks['database'] = false;
            $errors[] = 'Database: ' . $e->getMessage();
        }

        // Check Redis cache connection (if configured)
        if (config('cache.default') === 'redis') {
            try {
                Cache::store('redis')->get('health_check');
                $checks['redis'] = true;
            } catch (\Throwable $e) {
                $checks['redis'] = false;
                $errors[] = 'Redis: ' . $e->getMessage();
            }
        } else {
            $checks['redis'] = 'not configured';
        }

        // Check if storage is writable
        try {
            $testFile = storage_path('logs/.health_check');
            file_put_contents($testFile, 'test');
            unlink($testFile);
            $checks['storage'] = true;
        } catch (\Throwable $e) {
            $checks['storage'] = false;
            $errors[] = 'Storage: ' . $e->getMessage();
        }

        // Determine overall status
        $ready = empty($errors);
        
        return response()->json([
            'status' => $ready ? 'ready' : 'not ready',
            'checks' => $checks,
            'errors' => $errors,
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
})->name('health.ready');

// Startup probe - checks if the application has finished starting up
// Similar to readiness but with a longer timeout period
Route::get('/health/startup', function () {
    try {
        // Check if application key is set
        if (empty(config('app.key'))) {
            return response()->json([
                'status' => 'starting',
                'message' => 'Application key not set',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }

        // Check if we can connect to the database
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'starting',
                'message' => 'Database not ready',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }

        // If we reach here, the application has started successfully
        return response()->json([
            'status' => 'started',
            'timestamp' => now()->toIso8601String(),
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
})->name('health.startup');

// Detailed health check with metrics (for monitoring systems)
Route::get('/health/detailed', function () {
    try {
        $metrics = [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'version' => config('app.version', 'unknown'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'database' => [],
            'cache' => [],
            'storage' => [],
        ];

        // Database metrics
        try {
            $pdo = DB::connection()->getPdo();
            $metrics['database']['connected'] = true;
            $metrics['database']['driver'] = DB::connection()->getDriverName();
            $metrics['database']['name'] = DB::connection()->getDatabaseName();
        } catch (\Throwable $e) {
            $metrics['database']['connected'] = false;
            $metrics['database']['error'] = $e->getMessage();
        }

        // Cache metrics
        try {
            $cacheDriver = config('cache.default');
            $metrics['cache']['driver'] = $cacheDriver;
            Cache::put('health_check_test', 'ok', 10);
            $metrics['cache']['writable'] = Cache::get('health_check_test') === 'ok';
        } catch (\Throwable $e) {
            $metrics['cache']['error'] = $e->getMessage();
        }

        // Storage metrics
        $metrics['storage']['path'] = storage_path();
        $metrics['storage']['writable'] = is_writable(storage_path());
        
        if (function_exists('disk_free_space')) {
            $free = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $metrics['storage']['free_space'] = $free;
            $metrics['storage']['total_space'] = $total;
            $metrics['storage']['free_percentage'] = round(($free / $total) * 100, 2);
        }

        return response()->json([
            'status' => 'healthy',
            'metrics' => $metrics,
            'timestamp' => now()->toIso8601String(),
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
})->name('health.detailed');
