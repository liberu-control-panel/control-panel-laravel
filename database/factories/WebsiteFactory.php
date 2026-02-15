<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\User;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'server_id' => null,
            'name' => $this->faker->company(),
            'domain' => $this->faker->unique()->domainName(),
            'description' => $this->faker->sentence(),
            'platform' => $this->faker->randomElement(['wordpress', 'laravel', 'static', 'nodejs', 'custom']),
            'php_version' => $this->faker->randomElement(['8.1', '8.2', '8.3', '8.4']),
            'database_type' => $this->faker->randomElement(['mysql', 'mariadb', 'postgresql', 'none']),
            'document_root' => '/var/www/html',
            'status' => Website::STATUS_ACTIVE,
            'ssl_enabled' => $this->faker->boolean(80),
            'auto_ssl' => true,
            'uptime_percentage' => $this->faker->randomFloat(2, 95.00, 100.00),
            'last_checked_at' => now(),
            'average_response_time' => $this->faker->numberBetween(100, 500),
            'monthly_bandwidth' => $this->faker->numberBetween(1000000, 10000000000),
            'monthly_visitors' => $this->faker->numberBetween(100, 100000),
            'disk_usage_mb' => $this->faker->randomFloat(2, 10.00, 5000.00),
        ];
    }

    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Website::PLATFORM_WORDPRESS,
            'php_version' => '8.3',
            'database_type' => 'mysql',
        ]);
    }

    public function laravel(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Website::PLATFORM_LARAVEL,
            'php_version' => '8.3',
            'database_type' => 'mysql',
        ]);
    }

    public function static(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Website::PLATFORM_STATIC,
            'php_version' => null,
            'database_type' => 'none',
        ]);
    }

    public function withServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => Server::factory(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Website::STATUS_ACTIVE,
            'uptime_percentage' => 99.95,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Website::STATUS_PENDING,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Website::STATUS_MAINTENANCE,
        ]);
    }

    public function withSsl(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_enabled' => true,
            'auto_ssl' => true,
        ]);
    }

    public function withoutSsl(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_enabled' => false,
            'auto_ssl' => false,
        ]);
    }
}
