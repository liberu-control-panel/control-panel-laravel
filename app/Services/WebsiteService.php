<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsitePerformanceMetric;
use Exception;
use Illuminate\Support\Facades\Log;

class WebsiteService
{
    /**
     * Create a new website
     */
    public function create(array $data): array
    {
        try {
            // Set defaults
            $data['status'] = $data['status'] ?? Website::STATUS_PENDING;
            $data['platform'] = $data['platform'] ?? Website::PLATFORM_CUSTOM;
            $data['php_version'] = $data['php_version'] ?? '8.3';
            $data['database_type'] = $data['database_type'] ?? 'mysql';
            $data['document_root'] = $data['document_root'] ?? '/var/www/html';
            $data['ssl_enabled'] = $data['ssl_enabled'] ?? false;
            $data['auto_ssl'] = $data['auto_ssl'] ?? true;
            
            $website = Website::create($data);

            Log::info("Website created: {$website->domain}", ['website_id' => $website->id]);

            return [
                'success' => true,
                'message' => 'Website created successfully',
                'website' => $website->fresh(['server']),
            ];
        } catch (Exception $e) {
            Log::error("Failed to create website: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create website: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update a website
     */
    public function update(Website $website, array $data): array
    {
        try {
            $website->update($data);

            Log::info("Website updated: {$website->domain}", ['website_id' => $website->id]);

            return [
                'success' => true,
                'message' => 'Website updated successfully',
                'website' => $website->fresh(['server']),
            ];
        } catch (Exception $e) {
            Log::error("Failed to update website {$website->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update website: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a website
     */
    public function delete(Website $website): array
    {
        try {
            $domain = $website->domain;
            $website->delete();

            Log::info("Website deleted: {$domain}");

            return [
                'success' => true,
                'message' => 'Website deleted successfully',
            ];
        } catch (Exception $e) {
            Log::error("Failed to delete website {$website->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to delete website: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Record a performance metric for a website
     */
    public function recordPerformanceMetric(Website $website, array $data): WebsitePerformanceMetric
    {
        $metric = $website->performanceMetrics()->create([
            'response_time_ms' => $data['response_time_ms'] ?? 0,
            'status_code' => $data['status_code'] ?? 200,
            'uptime_status' => $data['uptime_status'] ?? true,
            'cpu_usage' => $data['cpu_usage'] ?? null,
            'memory_usage' => $data['memory_usage'] ?? null,
            'disk_usage' => $data['disk_usage'] ?? null,
            'bandwidth_used' => $data['bandwidth_used'] ?? 0,
            'visitors_count' => $data['visitors_count'] ?? 0,
            'checked_at' => $data['checked_at'] ?? now(),
        ]);

        // Update website aggregate metrics
        $this->updateWebsiteMetrics($website);

        return $metric;
    }

    /**
     * Update website aggregate metrics based on recent performance data
     */
    public function updateWebsiteMetrics(Website $website): void
    {
        $recentMetrics = $website->performanceMetrics()
            ->where('checked_at', '>=', now()->subDays(30))
            ->get();

        if ($recentMetrics->isEmpty()) {
            return;
        }

        $totalChecks = $recentMetrics->count();
        $successfulChecks = $recentMetrics->where('uptime_status', true)->count();
        
        $website->update([
            'uptime_percentage' => ($successfulChecks / $totalChecks) * 100,
            'average_response_time' => $recentMetrics->avg('response_time_ms'),
            'last_checked_at' => $recentMetrics->max('checked_at'),
        ]);
    }

    /**
     * Check website health and record metric
     */
    public function checkWebsiteHealth(Website $website): array
    {
        try {
            $startTime = microtime(true);
            
            // Simple HTTP check
            $url = ($website->ssl_enabled ? 'https://' : 'http://') . $website->domain;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            curl_close($ch);
            
            $uptimeStatus = ($statusCode >= 200 && $statusCode < 400);
            
            $this->recordPerformanceMetric($website, [
                'response_time_ms' => round($responseTime),
                'status_code' => $statusCode,
                'uptime_status' => $uptimeStatus,
                'checked_at' => now(),
            ]);
            
            return [
                'success' => true,
                'uptime_status' => $uptimeStatus,
                'status_code' => $statusCode,
                'response_time_ms' => round($responseTime),
            ];
        } catch (Exception $e) {
            Log::error("Health check failed for website {$website->id}: " . $e->getMessage());
            
            $this->recordPerformanceMetric($website, [
                'response_time_ms' => 0,
                'status_code' => 0,
                'uptime_status' => false,
                'checked_at' => now(),
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
