<?php

namespace Database\Factories;

use App\Models\KubernetesNode;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KubernetesNode>
 */
class KubernetesNodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = KubernetesNode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'name' => 'node-' . fake()->unique()->bothify('??##'),
            'uid' => fake()->uuid(),
            'kubernetes_version' => 'v1.' . fake()->numberBetween(24, 30) . '.' . fake()->numberBetween(0, 10),
            'container_runtime' => 'containerd://' . fake()->numerify('1.#.#'),
            'os_image' => fake()->randomElement([
                'Ubuntu 20.04 LTS',
                'Ubuntu 22.04 LTS',
                'Ubuntu 24.04 LTS',
                'Amazon Linux 2',
                'CentOS Linux 8',
            ]),
            'kernel_version' => fake()->numerify('#.##.#-##-generic'),
            'architecture' => fake()->randomElement(['amd64', 'arm64']),
            'status' => fake()->randomElement([
                KubernetesNode::STATUS_READY,
                KubernetesNode::STATUS_NOT_READY,
                KubernetesNode::STATUS_UNKNOWN,
                // Note: STATUS_SCHEDULING_DISABLED is set via cordoned() state method
            ]),
            'schedulable' => fake()->boolean(80), // 80% chance of being schedulable
            'labels' => [
                'kubernetes.io/hostname' => 'node-' . fake()->bothify('??##'),
                'node-role.kubernetes.io/worker' => 'true',
                'beta.kubernetes.io/arch' => 'amd64',
                'beta.kubernetes.io/os' => 'linux',
            ],
            'annotations' => [
                'node.alpha.kubernetes.io/ttl' => '0',
            ],
            'taints' => [],
            'addresses' => [
                ['type' => 'InternalIP', 'address' => fake()->localIpv4()],
                ['type' => 'Hostname', 'address' => 'node-' . fake()->bothify('??##')],
            ],
            'capacity' => [
                'cpu' => (string) fake()->numberBetween(2, 32),
                'memory' => fake()->numberBetween(4, 128) . 'Gi',
                'pods' => (string) fake()->numberBetween(100, 250),
            ],
            'allocatable' => [
                'cpu' => fake()->numberBetween(1900, 31900) . 'm',
                'memory' => fake()->numberBetween(3, 127) . 'Gi',
                'pods' => (string) fake()->numberBetween(90, 240),
            ],
            'conditions' => [
                [
                    'type' => 'Ready',
                    'status' => 'True',
                    'lastHeartbeatTime' => now()->subMinutes(fake()->numberBetween(1, 5))->toIso8601String(),
                    'lastTransitionTime' => now()->subHours(fake()->numberBetween(1, 24))->toIso8601String(),
                    'reason' => 'KubeletReady',
                    'message' => 'kubelet is posting ready status',
                ],
                [
                    'type' => 'MemoryPressure',
                    'status' => 'False',
                    'lastHeartbeatTime' => now()->subMinutes(fake()->numberBetween(1, 5))->toIso8601String(),
                    'lastTransitionTime' => now()->subHours(fake()->numberBetween(1, 24))->toIso8601String(),
                    'reason' => 'KubeletHasSufficientMemory',
                    'message' => 'kubelet has sufficient memory available',
                ],
                [
                    'type' => 'DiskPressure',
                    'status' => 'False',
                    'lastHeartbeatTime' => now()->subMinutes(fake()->numberBetween(1, 5))->toIso8601String(),
                    'lastTransitionTime' => now()->subHours(fake()->numberBetween(1, 24))->toIso8601String(),
                    'reason' => 'KubeletHasNoDiskPressure',
                    'message' => 'kubelet has no disk pressure',
                ],
            ],
            'last_heartbeat_time' => now()->subMinutes(fake()->numberBetween(1, 5)),
        ];
    }

    /**
     * Indicate that the node is ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KubernetesNode::STATUS_READY,
            'schedulable' => true,
        ]);
    }

    /**
     * Indicate that the node is not ready.
     */
    public function notReady(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KubernetesNode::STATUS_NOT_READY,
        ]);
    }

    /**
     * Indicate that the node is cordoned (unschedulable).
     */
    public function cordoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedulable' => false,
            'status' => KubernetesNode::STATUS_SCHEDULING_DISABLED,
        ]);
    }

    /**
     * Indicate that the node is a master/control-plane node.
     */
    public function master(): static
    {
        return $this->state(fn (array $attributes) => [
            'labels' => array_merge($attributes['labels'] ?? [], [
                'node-role.kubernetes.io/control-plane' => 'true',
                'node-role.kubernetes.io/master' => 'true',
            ]),
            'taints' => [
                [
                    'key' => 'node-role.kubernetes.io/control-plane',
                    'effect' => 'NoSchedule',
                ],
            ],
        ]);
    }
}
