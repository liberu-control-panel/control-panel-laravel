<?php

namespace Tests\Unit\Services;

use App\Models\KubernetesNode;
use App\Models\Server;
use App\Services\KubernetesNodeService;
use App\Services\SshConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class KubernetesNodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_parse_cpu_capacity_with_cores()
    {
        $node = new KubernetesNode([
            'capacity' => ['cpu' => '4'],
        ]);

        $this->assertEquals(4.0, $node->getCpuCapacity());
    }

    public function test_parse_cpu_capacity_with_millicores()
    {
        $node = new KubernetesNode([
            'capacity' => ['cpu' => '4000m'],
        ]);

        $this->assertEquals(4.0, $node->getCpuCapacity());
    }

    public function test_parse_memory_capacity_in_gi()
    {
        $node = new KubernetesNode([
            'capacity' => ['memory' => '16Gi'],
        ]);

        $this->assertEquals(16.0, $node->getMemoryCapacity());
    }

    public function test_parse_memory_capacity_in_mi()
    {
        $node = new KubernetesNode([
            'capacity' => ['memory' => '16384Mi'],
        ]);

        $this->assertEquals(16.0, $node->getMemoryCapacity());
    }

    public function test_node_is_ready_status()
    {
        $node = new KubernetesNode([
            'status' => KubernetesNode::STATUS_READY,
        ]);

        $this->assertTrue($node->isReady());
    }

    public function test_node_is_not_ready_status()
    {
        $node = new KubernetesNode([
            'status' => KubernetesNode::STATUS_NOT_READY,
        ]);

        $this->assertFalse($node->isReady());
    }

    public function test_node_is_schedulable()
    {
        $node = new KubernetesNode([
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => true,
        ]);

        $this->assertTrue($node->isSchedulable());
    }

    public function test_node_is_not_schedulable_when_cordoned()
    {
        $node = new KubernetesNode([
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => false,
        ]);

        $this->assertFalse($node->isSchedulable());
    }

    public function test_node_has_label()
    {
        $node = new KubernetesNode([
            'labels' => [
                'node-role.kubernetes.io/worker' => 'true',
                'custom-label' => 'value',
            ],
        ]);

        $this->assertTrue($node->hasLabel('node-role.kubernetes.io/worker'));
        $this->assertTrue($node->hasLabel('custom-label', 'value'));
        $this->assertFalse($node->hasLabel('non-existent'));
    }

    public function test_node_has_taint()
    {
        $node = new KubernetesNode([
            'taints' => [
                ['key' => 'node.kubernetes.io/unreachable', 'effect' => 'NoSchedule'],
                ['key' => 'custom-taint', 'effect' => 'NoExecute'],
            ],
        ]);

        $this->assertTrue($node->hasTaint('node.kubernetes.io/unreachable'));
        $this->assertTrue($node->hasTaint('custom-taint', 'NoExecute'));
        $this->assertFalse($node->hasTaint('non-existent'));
    }

    public function test_get_node_role_master()
    {
        $node = new KubernetesNode([
            'labels' => [
                'node-role.kubernetes.io/control-plane' => 'true',
            ],
        ]);

        $this->assertEquals('master', $node->getRole());
    }

    public function test_get_node_role_worker()
    {
        $node = new KubernetesNode([
            'labels' => [
                'node-role.kubernetes.io/worker' => 'true',
            ],
        ]);

        $this->assertEquals('worker', $node->getRole());
    }

    public function test_get_node_role_default_worker()
    {
        $node = new KubernetesNode([
            'labels' => [],
        ]);

        $this->assertEquals('worker', $node->getRole());
    }

    public function test_update_or_create_node()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
        ]);

        $nodeData = [
            'server_id' => $server->id,
            'name' => 'test-node-1',
            'uid' => 'test-uid-123',
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => true,
            'kubernetes_version' => 'v1.28.0',
        ];

        $sshService = Mockery::mock(SshConnectionService::class);
        $service = new KubernetesNodeService($sshService);

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('updateOrCreateNode');
        $method->setAccessible(true);

        $node = $method->invoke($service, $server, $nodeData);

        $this->assertInstanceOf(KubernetesNode::class, $node);
        $this->assertEquals('test-node-1', $node->name);
        $this->assertEquals('test-uid-123', $node->uid);
        $this->assertEquals(KubernetesNode::STATUS_READY, $node->status);
    }

    public function test_parse_node_data()
    {
        $apiResponse = [
            'metadata' => [
                'name' => 'worker-1',
                'uid' => 'abc-123',
                'labels' => [
                    'kubernetes.io/hostname' => 'worker-1',
                    'node-role.kubernetes.io/worker' => 'true',
                ],
                'annotations' => [
                    'annotation-key' => 'annotation-value',
                ],
            ],
            'spec' => [
                'taints' => [],
            ],
            'status' => [
                'conditions' => [
                    [
                        'type' => 'Ready',
                        'status' => 'True',
                        'lastHeartbeatTime' => '2026-02-15T23:00:00Z',
                    ],
                ],
                'addresses' => [
                    ['type' => 'InternalIP', 'address' => '10.0.0.1'],
                ],
                'capacity' => [
                    'cpu' => '4',
                    'memory' => '16Gi',
                ],
                'allocatable' => [
                    'cpu' => '3900m',
                    'memory' => '15Gi',
                ],
                'nodeInfo' => [
                    'kubeletVersion' => 'v1.28.0',
                    'containerRuntimeVersion' => 'containerd://1.7.0',
                    'osImage' => 'Ubuntu 22.04 LTS',
                    'kernelVersion' => '5.15.0-91-generic',
                    'architecture' => 'amd64',
                ],
            ],
        ];

        $sshService = Mockery::mock(SshConnectionService::class);
        $service = new KubernetesNodeService($sshService);

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseNodeData');
        $method->setAccessible(true);

        $result = $method->invoke($service, $apiResponse);

        $this->assertEquals('worker-1', $result['name']);
        $this->assertEquals('abc-123', $result['uid']);
        $this->assertEquals(KubernetesNode::STATUS_READY, $result['status']);
        $this->assertTrue($result['schedulable']);
        $this->assertEquals('v1.28.0', $result['kubernetes_version']);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('capacity', $result);
    }
}
