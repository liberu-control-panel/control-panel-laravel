<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebsiteApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_websites()
    {
        Website::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/websites');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'name',
                             'domain',
                             'platform',
                             'status',
                             'uptime_percentage',
                         ]
                     ],
                     'total',
                     'per_page',
                 ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_website()
    {
        $websiteData = [
            'name' => 'New Website',
            'domain' => 'newsite.example.com',
            'description' => 'A new test website',
            'platform' => 'wordpress',
            'php_version' => '8.3',
            'database_type' => 'mysql',
            'ssl_enabled' => true,
            'auto_ssl' => true,
        ];

        $response = $this->postJson('/api/websites', $websiteData);

        $response->assertStatus(201)
                 ->assertJsonFragment([
                     'message' => 'Website created successfully',
                 ])
                 ->assertJsonStructure([
                     'message',
                     'website' => [
                         'id',
                         'name',
                         'domain',
                         'platform',
                         'status',
                     ]
                 ]);

        $this->assertDatabaseHas('websites', [
            'domain' => 'newsite.example.com',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_get_website_details()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/websites/{$website->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'id' => $website->id,
                     'domain' => $website->domain,
                 ]);
    }

    public function test_can_update_website()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/websites/{$website->id}", [
            'name' => 'Updated Name',
            'status' => Website::STATUS_ACTIVE,
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'message' => 'Website updated successfully',
                 ]);

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'name' => 'Updated Name',
            'status' => Website::STATUS_ACTIVE,
        ]);
    }

    public function test_can_delete_website()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/websites/{$website->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'message' => 'Website deleted successfully',
                 ]);

        $this->assertDatabaseMissing('websites', [
            'id' => $website->id,
        ]);
    }

    public function test_cannot_access_other_users_website()
    {
        $otherUser = User::factory()->create();
        $otherWebsite = Website::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/websites/{$otherWebsite->id}");

        $response->assertStatus(403);
    }

    public function test_can_get_performance_metrics()
    {
        $website = Website::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create some performance metrics
        $website->performanceMetrics()->create([
            'response_time_ms' => 250,
            'status_code' => 200,
            'uptime_status' => true,
            'checked_at' => now(),
        ]);

        $response = $this->getJson("/api/websites/{$website->id}/performance");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'website' => ['id', 'name', 'domain'],
                     'metrics',
                     'summary' => [
                         'uptime_percentage',
                         'average_response_time',
                         'total_checks',
                         'successful_checks',
                         'failed_checks',
                     ]
                 ]);
    }

    public function test_can_get_website_statistics()
    {
        Website::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'status' => Website::STATUS_ACTIVE,
        ]);

        Website::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => Website::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/websites-statistics');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_websites',
                     'active_websites',
                     'total_visitors',
                     'total_bandwidth',
                     'average_uptime',
                     'websites_by_platform',
                     'websites_by_status',
                 ])
                 ->assertJsonFragment([
                     'total_websites' => 7,
                     'active_websites' => 5,
                 ]);
    }

    public function test_website_creation_validates_required_fields()
    {
        $response = $this->postJson('/api/websites', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'domain']);
    }

    public function test_website_domain_must_be_unique()
    {
        $existingWebsite = Website::factory()->create([
            'user_id' => $this->user->id,
            'domain' => 'existing.example.com',
        ]);

        $response = $this->postJson('/api/websites', [
            'name' => 'New Website',
            'domain' => 'existing.example.com', // Duplicate domain
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['domain']);
    }
}
