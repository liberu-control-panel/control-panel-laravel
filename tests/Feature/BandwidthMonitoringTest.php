<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ResourceUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandwidthMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->domain = Domain::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_resource_usage_can_be_created_for_domain(): void
    {
        $usage = ResourceUsage::create([
            'user_id'         => $this->user->id,
            'domain_id'       => $this->domain->id,
            'disk_usage'      => 500,
            'bandwidth_usage' => 1024,
            'month'           => now()->month,
            'year'            => now()->year,
        ]);

        $this->assertDatabaseHas('resource_usage', [
            'domain_id'       => $this->domain->id,
            'disk_usage'      => 500,
            'bandwidth_usage' => 1024,
        ]);
    }

    public function test_resource_usage_belongs_to_domain(): void
    {
        $usage = ResourceUsage::factory()->create([
            'domain_id' => $this->domain->id,
        ]);

        $this->assertTrue($usage->domain->is($this->domain));
    }

    public function test_domain_has_resource_usage_relationship(): void
    {
        ResourceUsage::factory()->count(3)->create([
            'user_id'   => $this->user->id,
            'domain_id' => $this->domain->id,
        ]);

        $this->assertCount(3, $this->domain->resourceUsage);
    }

    public function test_for_domain_scope_filters_by_domain(): void
    {
        $otherDomain = Domain::factory()->create(['user_id' => $this->user->id]);

        ResourceUsage::factory()->create(['user_id' => $this->user->id, 'domain_id' => $this->domain->id]);
        ResourceUsage::factory()->create(['user_id' => $this->user->id, 'domain_id' => $otherDomain->id]);

        $usages = ResourceUsage::forDomain($this->domain->id)->get();

        $this->assertCount(1, $usages);
        $this->assertEquals($this->domain->id, $usages->first()->domain_id);
    }

    public function test_for_month_scope_filters_by_month_and_year(): void
    {
        ResourceUsage::factory()->create([
            'user_id'   => $this->user->id,
            'domain_id' => $this->domain->id,
            'month'     => 1,
            'year'      => 2026,
        ]);
        ResourceUsage::factory()->create([
            'user_id'   => $this->user->id,
            'domain_id' => $this->domain->id,
            'month'     => 2,
            'year'      => 2026,
        ]);

        $usages = ResourceUsage::forDomain($this->domain->id)->forMonth(1, 2026)->get();

        $this->assertCount(1, $usages);
        $this->assertEquals(1, $usages->first()->month);
    }
}
