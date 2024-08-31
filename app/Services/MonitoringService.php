<?php

namespace App\Services;

use App\Models\ResourceUsage;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MonitoringService
{
    public function collectResourceUsage(User $user)
    {
        // Simulating resource usage collection
        // In a real-world scenario, you would collect this data from your server or hosting provider's API
        $diskUsage = rand(0, 100);
        $bandwidthUsage = rand(0, 100);
        $cpuUsage = rand(0, 100);
        $memoryUsage = rand(0, 100);

        ResourceUsage::create([
            'user_id' => $user->id,
            'disk_usage' => $diskUsage,
            'bandwidth_usage' => $bandwidthUsage,
            'cpu_usage' => $cpuUsage,
            'memory_usage' => $memoryUsage,
            'month' => now()->format('F'),
            'year' => now()->year,
        ]);
    }

    public function logAccess(User $user, string $action, string $ipAddress)
    {
        AccessLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $ipAddress,
        ]);
    }

    public function getResourceUsageStats(User $user, int $days = 30)
    {
        return ResourceUsage::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('AVG(disk_usage) as avg_disk_usage'),
                DB::raw('AVG(bandwidth_usage) as avg_bandwidth_usage'),
                DB::raw('AVG(cpu_usage) as avg_cpu_usage'),
                DB::raw('AVG(memory_usage) as avg_memory_usage')
            )
            ->first();
    }

    public function getRecentAccessLogs(User $user, int $limit = 10)
    {
        return AccessLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}