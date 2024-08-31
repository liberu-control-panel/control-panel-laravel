<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\ResourceUsage;
use App\Models\AccessLog;
use App\Services\MonitoringService;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_usage_collection()
    {
        $user = User::factory()->create();
        $monitoringService = new MonitoringService();

        $monitoringService->collectResourceUsage($user);

        $this->assertDatabaseHas('resource_usage', [
            'user_id' => $user->id,
        ]);

        $resourceUsage = ResourceUsage::where('user_id', $user->id)->first();
        $this->assertNotNull($resourceUsage);
        $this->assertIsNumeric($resourceUsage->disk_usage);
        $this->assertIsNumeric($resourceUsage->bandwidth_usage);
        $this->assertIsNumeric($resourceUsage->cpu_usage);
        $this->assertIsNumeric($resourceUsage->memory_usage);
    }

    public function test_access_log_creation()
    {
        $user = User::factory()->create();
        $monitoringService = new MonitoringService();

        $action = 'login';
        $ipAddress = '192.168.1.1';

        $monitoringService->logAccess($user, $action, $ipAddress);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $ipAddress,
        ]);
    }

    public function test_resource_usage_stats()
    {
        $user = User::factory()->create();
        $monitoringService = new MonitoringService();

        // Create some sample resource usage data
        for ($i = 0; $i < 5; $i++) {
            $monitoringService->collectResourceUsage($user);
        }

        $stats = $monitoringService->getResourceUsageStats($user);

        $this->assertNotNull($stats);
        $this->assertIsNumeric($stats->avg_disk_usage);
        $this->assertIsNumeric($stats->avg_bandwidth_usage);
        $this->assertIsNumeric($stats->avg_cpu_usage);
        $this->assertIsNumeric($stats->avg_memory_usage);
    }

    public function test_recent_access_logs()
    {
        $user = User::factory()->create();

        $monitoringService = new MonitoringService();

        // Create some sample access logs
        for ($i = 0; $i < 15; $i++) {
            $monitoringService->logAccess($user, "action_$i", "192.168.1.$i");
        }

        $recentLogs = $monitoringService->getRecentAccessLogs($user);

        $this->assertCount(10, $recentLogs);
        $this->assertEquals("action_14", $recentLogs->first()->action);
        $this->assertEquals("action_5", $recentLogs->last()->action);
    }
}