<?php

namespace Database\Factories;

use App\Models\FirewallRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FirewallRuleFactory extends Factory
{
    protected $model = FirewallRule::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'action' => $this->faker->randomElement(['allow', 'deny']),
            'ip_address' => $this->faker->ipv4(),
            'protocol' => $this->faker->randomElement(['tcp', 'udp', 'icmp', 'all']),
            'port' => $this->faker->optional()->numberBetween(1, 65535),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 1000),
        ];
    }

    public function deny(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'deny',
        ]);
    }

    public function allow(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'allow',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
