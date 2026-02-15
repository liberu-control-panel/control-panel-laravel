<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'hostname' => $this->faker->domainName(),
            'port' => 22,
            'ip_address' => $this->faker->ipv4(),
            'type' => $this->faker->randomElement([Server::TYPE_KUBERNETES, Server::TYPE_DOCKER, Server::TYPE_STANDALONE]),
            'status' => Server::STATUS_ACTIVE,
            'description' => $this->faker->sentence(),
            'metadata' => [],
            'is_default' => false,
            'max_domains' => $this->faker->numberBetween(10, 100),
        ];
    }

    public function kubernetes(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Server::TYPE_KUBERNETES,
        ]);
    }

    public function docker(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Server::TYPE_DOCKER,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
