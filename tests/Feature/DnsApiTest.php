<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Domain;
use App\Models\DnsSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class DnsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $domain;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
        
        // Create a test domain
        $this->domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'domain_name' => 'test.com',
            'registration_date' => now(),
            'expiration_date' => now()->addYear(),
        ]);
    }

    /**
     * Test listing DNS records
     */
    public function test_can_list_dns_records()
    {
        Sanctum::actingAs($this->user);

        // Create some DNS records
        DnsSetting::factory()->count(3)->create([
            'domain_id' => $this->domain->id,
        ]);

        $response = $this->getJson('/api/dns');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'domain_id',
                            'record_type',
                            'name',
                            'value',
                            'ttl',
                        ]
                    ]
                ]);
    }

    /**
     * Test creating A record
     */
    public function test_can_create_a_record()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/dns', [
            'domain_id' => $this->domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => '192.0.2.1',
            'ttl' => 3600,
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'DNS record created successfully',
                ]);

        $this->assertDatabaseHas('dns_settings', [
            'domain_id' => $this->domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => '192.0.2.1',
        ]);
    }

    /**
     * Test creating MX record with priority
     */
    public function test_can_create_mx_record()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/dns', [
            'domain_id' => $this->domain->id,
            'record_type' => 'MX',
            'name' => '@',
            'value' => 'mail.test.com',
            'priority' => 10,
            'ttl' => 3600,
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                ]);

        $this->assertDatabaseHas('dns_settings', [
            'domain_id' => $this->domain->id,
            'record_type' => 'MX',
            'priority' => 10,
        ]);
    }

    /**
     * Test validation fails for invalid A record
     */
    public function test_validation_fails_for_invalid_a_record()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/dns', [
            'domain_id' => $this->domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => 'not-an-ip',
            'ttl' => 3600,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                ]);
    }

    /**
     * Test validation fails for MX record without priority
     */
    public function test_validation_fails_for_mx_without_priority()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/dns', [
            'domain_id' => $this->domain->id,
            'record_type' => 'MX',
            'name' => '@',
            'value' => 'mail.test.com',
            'ttl' => 3600,
            // Missing priority
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test updating DNS record
     */
    public function test_can_update_dns_record()
    {
        Sanctum::actingAs($this->user);

        $dnsRecord = DnsSetting::factory()->create([
            'domain_id' => $this->domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => '192.0.2.1',
        ]);

        $response = $this->putJson("/api/dns/{$dnsRecord->id}", [
            'value' => '192.0.2.2',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        $this->assertDatabaseHas('dns_settings', [
            'id' => $dnsRecord->id,
            'value' => '192.0.2.2',
        ]);
    }

    /**
     * Test deleting DNS record
     */
    public function test_can_delete_dns_record()
    {
        Sanctum::actingAs($this->user);

        $dnsRecord = DnsSetting::factory()->create([
            'domain_id' => $this->domain->id,
        ]);

        $response = $this->deleteJson("/api/dns/{$dnsRecord->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        $this->assertDatabaseMissing('dns_settings', [
            'id' => $dnsRecord->id,
        ]);
    }

    /**
     * Test bulk creating DNS records
     */
    public function test_can_bulk_create_dns_records()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/dns/bulk', [
            'domain_id' => $this->domain->id,
            'records' => [
                [
                    'record_type' => 'A',
                    'name' => '@',
                    'value' => '192.0.2.1',
                    'ttl' => 3600,
                ],
                [
                    'record_type' => 'A',
                    'name' => 'www',
                    'value' => '192.0.2.1',
                    'ttl' => 3600,
                ],
                [
                    'record_type' => 'MX',
                    'name' => '@',
                    'value' => 'mail.test.com',
                    'priority' => 10,
                    'ttl' => 3600,
                ],
            ],
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                ]);

        $this->assertDatabaseCount('dns_settings', 3);
    }

    /**
     * Test cannot access other user's DNS records
     */
    public function test_cannot_access_other_users_dns_records()
    {
        $otherUser = User::factory()->create();
        $otherDomain = Domain::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        
        $otherDnsRecord = DnsSetting::factory()->create([
            'domain_id' => $otherDomain->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/dns/{$otherDnsRecord->id}");
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/dns/{$otherDnsRecord->id}");
        $response->assertStatus(403);
    }

    /**
     * Test DNS validation endpoint
     */
    public function test_can_validate_dns_record()
    {
        Sanctum::actingAs($this->user);

        // Valid record
        $response = $this->postJson('/api/dns/validate', [
            'record_type' => 'A',
            'name' => '@',
            'value' => '192.0.2.1',
            'ttl' => 3600,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'valid' => true,
                ]);

        // Invalid record
        $response = $this->postJson('/api/dns/validate', [
            'record_type' => 'A',
            'name' => '@',
            'value' => 'invalid-ip',
            'ttl' => 3600,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'valid' => false,
                ]);
    }

    /**
     * Test TTL validation
     */
    public function test_ttl_validation()
    {
        Sanctum::actingAs($this->user);

        // TTL too low
        $response = $this->postJson('/api/dns', [
            'domain_id' => $this->domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => '192.0.2.1',
            'ttl' => 30, // Below minimum of 60
        ]);

        $response->assertStatus(422);

        // TTL too high
        $response = $this->postJson('/api/dns', [
            'domain_id' => $this->domain->id,
            'record_type' => 'A',
            'name' => '@',
            'value' => '192.0.2.1',
            'ttl' => 90000, // Above maximum of 86400
        ]);

        $response->assertStatus(422);
    }
}
