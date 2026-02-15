<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Models\Server;
use App\Services\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_website_can_be_created()
    {
        $websiteData = [
            'name' => 'Test Website',
            'domain' => 'test.example.com',
            'description' => 'A test website',
            'platform' => 'laravel',
            'php_version' => '8.3',
            'database_type' => 'mysql',
            'ssl_enabled' => true,
        ];

        $websiteService = new WebsiteService();
        $result = $websiteService->create(array_merge($websiteData, ['user_id' => $this->user->id]));

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('websites', [
            'domain' => 'test.example.com',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_website_can_be_updated()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $websiteService = new WebsiteService();
        $result = $websiteService->update($website, [
            'name' => 'Updated Website Name',
            'status' => Website::STATUS_ACTIVE,
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'name' => 'Updated Website Name',
            'status' => Website::STATUS_ACTIVE,
        ]);
    }

    public function test_website_can_be_deleted()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $websiteService = new WebsiteService();
        $result = $websiteService->delete($website);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('websites', [
            'id' => $website->id,
        ]);
    }

    public function test_performance_metric_can_be_recorded()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $websiteService = new WebsiteService();
        $metric = $websiteService->recordPerformanceMetric($website, [
            'response_time_ms' => 250,
            'status_code' => 200,
            'uptime_status' => true,
            'checked_at' => now(),
        ]);

        $this->assertDatabaseHas('website_performance_metrics', [
            'website_id' => $website->id,
            'response_time_ms' => 250,
            'status_code' => 200,
            'uptime_status' => true,
        ]);
    }

    public function test_website_metrics_are_updated_after_recording()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
            'uptime_percentage' => 100.00,
        ]);

        $websiteService = new WebsiteService();
        
        // Record multiple metrics
        for ($i = 0; $i < 10; $i++) {
            $websiteService->recordPerformanceMetric($website, [
                'response_time_ms' => 200 + ($i * 10),
                'status_code' => 200,
                'uptime_status' => true,
                'checked_at' => now()->subMinutes(10 - $i),
            ]);
        }

        // Refresh website to get updated metrics
        $website->refresh();

        // Uptime should still be 100% since all checks passed
        $this->assertEquals(100.00, $website->uptime_percentage);
        
        // Average response time should be around 245ms (200 + 45/2)
        $this->assertGreaterThan(230, $website->average_response_time);
        $this->assertLessThan(260, $website->average_response_time);
    }

    public function test_user_can_only_see_their_own_websites()
    {
        $otherUser = User::factory()->create();

        $myWebsite = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $otherWebsite = Website::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/websites');

        $response->assertStatus(200)
                 ->assertJsonFragment(['domain' => $myWebsite->domain])
                 ->assertJsonMissing(['domain' => $otherWebsite->domain]);
    }

    public function test_website_health_status_is_correct()
    {
        $excellentWebsite = Website::factory()->create([
            'user_id' => $this->user->id,
            'uptime_percentage' => 99.95,
            'status' => Website::STATUS_ACTIVE,
        ]);

        $goodWebsite = Website::factory()->create([
            'user_id' => $this->user->id,
            'uptime_percentage' => 99.50,
            'status' => Website::STATUS_ACTIVE,
        ]);

        $fairWebsite = Website::factory()->create([
            'user_id' => $this->user->id,
            'uptime_percentage' => 97.00,
            'status' => Website::STATUS_ACTIVE,
        ]);

        $poorWebsite = Website::factory()->create([
            'user_id' => $this->user->id,
            'uptime_percentage' => 90.00,
            'status' => Website::STATUS_ACTIVE,
        ]);

        $this->assertEquals('excellent', $excellentWebsite->getHealthStatus());
        $this->assertEquals('good', $goodWebsite->getHealthStatus());
        $this->assertEquals('fair', $fairWebsite->getHealthStatus());
        $this->assertEquals('poor', $poorWebsite->getHealthStatus());
    }
}
