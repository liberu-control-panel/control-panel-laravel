<?php

namespace Tests\Feature;

use App\Models\KubernetesNode;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KubernetesNodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_kubernetes_node()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
            'name' => 'test-k8s-server',
        ]);

        $node = KubernetesNode::create([
            'server_id' => $server->id,
            'name' => 'test-node',
            'uid' => 'unique-id-123',
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => true,
            'kubernetes_version' => 'v1.28.0',
            'architecture' => 'amd64',
            'capacity' => [
                'cpu' => '4',
                'memory' => '16Gi',
            ],
            'allocatable' => [
                'cpu' => '3900m',
                'memory' => '15Gi',
            ],
        ]);

        $this->assertDatabaseHas('kubernetes_nodes', [
            'name' => 'test-node',
            'server_id' => $server->id,
            'status' => KubernetesNode::STATUS_READY,
        ]);

        $this->assertTrue($node->isReady());
        $this->assertTrue($node->isSchedulable());
    }

    public function test_node_belongs_to_server()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
        ]);

        $node = KubernetesNode::factory()->create([
            'server_id' => $server->id,
        ]);

        $this->assertInstanceOf(Server::class, $node->server);
        $this->assertEquals($server->id, $node->server->id);
    }

    public function test_server_has_many_kubernetes_nodes()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
        ]);

        KubernetesNode::factory()->count(3)->create([
            'server_id' => $server->id,
        ]);

        $this->assertCount(3, $server->kubernetesNodes);
    }

    public function test_ready_scope()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
        ]);

        KubernetesNode::factory()->create([
            'server_id' => $server->id,
            'status' => KubernetesNode::STATUS_READY,
        ]);

        KubernetesNode::factory()->create([
            'server_id' => $server->id,
            'status' => KubernetesNode::STATUS_NOT_READY,
        ]);

        $readyNodes = KubernetesNode::ready()->get();

        $this->assertCount(1, $readyNodes);
        $this->assertEquals(KubernetesNode::STATUS_READY, $readyNodes->first()->status);
    }

    public function test_schedulable_scope()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
        ]);

        KubernetesNode::factory()->create([
            'server_id' => $server->id,
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => true,
        ]);

        KubernetesNode::factory()->create([
            'server_id' => $server->id,
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => false,
        ]);

        KubernetesNode::factory()->create([
            'server_id' => $server->id,
            'status' => KubernetesNode::STATUS_NOT_READY,
            'schedulable' => true,
        ]);

        $schedulableNodes = KubernetesNode::schedulable()->get();

        $this->assertCount(1, $schedulableNodes);
        $this->assertTrue($schedulableNodes->first()->schedulable);
        $this->assertEquals(KubernetesNode::STATUS_READY, $schedulableNodes->first()->status);
    }

    public function test_soft_delete()
    {
        $server = Server::factory()->create([
            'type' => Server::TYPE_KUBERNETES,
        ]);

        $node = KubernetesNode::factory()->create([
            'server_id' => $server->id,
        ]);

        $nodeId = $node->id;

        $node->delete();

        $this->assertSoftDeleted('kubernetes_nodes', [
            'id' => $nodeId,
        ]);

        $this->assertCount(0, KubernetesNode::all());
        $this->assertCount(1, KubernetesNode::withTrashed()->get());
    }
}
