<?php

namespace Tests\Unit\Services;

use App\Models\FirewallRule;
use App\Models\User;
use App\Services\FirewallService;
use App\Services\DeploymentDetectionService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class FirewallServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $detectionService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->detectionService = Mockery::mock(DeploymentDetectionService::class);
        $this->service = new FirewallService($this->detectionService);
        
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_firewall_rule()
    {
        $this->detectionService->shouldReceive('isKubernetes')->andReturn(false);
        $this->detectionService->shouldReceive('isDocker')->andReturn(false);

        $data = [
            'user_id' => $this->user->id,
            'name' => 'Block Bad IP',
            'action' => 'deny',
            'ip_address' => '192.168.1.100',
            'protocol' => 'tcp',
            'port' => 22,
            'is_active' => false, // Don't actually apply during test
        ];

        $rule = $this->service->createRule($data);

        $this->assertInstanceOf(FirewallRule::class, $rule);
        $this->assertEquals('Block Bad IP', $rule->name);
        $this->assertEquals('deny', $rule->action);
        $this->assertEquals('192.168.1.100', $rule->ip_address);
    }

    /** @test */
    public function it_validates_ip_address_format()
    {
        $this->detectionService->shouldReceive('isKubernetes')->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid IP address or CIDR notation');

        $data = [
            'user_id' => $this->user->id,
            'name' => 'Invalid Rule',
            'action' => 'deny',
            'ip_address' => 'not-an-ip',
            'protocol' => 'tcp',
            'is_active' => false,
        ];

        $this->service->createRule($data);
    }

    /** @test */
    public function it_accepts_cidr_notation()
    {
        $this->detectionService->shouldReceive('isKubernetes')->andReturn(false);

        $data = [
            'user_id' => $this->user->id,
            'name' => 'Block Network',
            'action' => 'deny',
            'ip_address' => '192.168.1.0/24',
            'protocol' => 'all',
            'is_active' => false,
        ];

        $rule = $this->service->createRule($data);

        $this->assertEquals('192.168.1.0/24', $rule->ip_address);
    }

    /** @test */
    public function it_can_get_active_rules_for_user()
    {
        // Create some rules
        FirewallRule::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'priority' => 100,
        ]);
        FirewallRule::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'priority' => 50,
        ]);
        FirewallRule::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        $rules = $this->service->getActiveRules($this->user);

        $this->assertCount(2, $rules);
        // Should be ordered by priority
        $this->assertEquals(50, $rules[0]['priority']);
        $this->assertEquals(100, $rules[1]['priority']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
