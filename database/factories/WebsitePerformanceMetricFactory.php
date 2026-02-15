<?php

namespace Database\Factories;

use App\Models\WebsitePerformanceMetric;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebsitePerformanceMetricFactory extends Factory
{
    protected $model = WebsitePerformanceMetric::class;

    public function definition(): array
    {
        $uptime = $this->faker->boolean(95);
        
        return [
            'website_id' => Website::factory(),
            'response_time_ms' => $uptime ? $this->faker->numberBetween(50, 500) : 0,
            'status_code' => $uptime ? 200 : $this->faker->randomElement([500, 502, 503, 504]),
            'uptime_status' => $uptime,
            'cpu_usage' => $this->faker->randomFloat(2, 10.00, 80.00),
            'memory_usage' => $this->faker->randomFloat(2, 20.00, 90.00),
            'disk_usage' => $this->faker->randomFloat(2, 100.00, 5000.00),
            'bandwidth_used' => $this->faker->numberBetween(1000000, 100000000),
            'visitors_count' => $this->faker->numberBetween(10, 1000),
            'checked_at' => now(),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_time_ms' => $this->faker->numberBetween(50, 300),
            'status_code' => 200,
            'uptime_status' => true,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_time_ms' => 0,
            'status_code' => $this->faker->randomElement([500, 502, 503, 504]),
            'uptime_status' => false,
        ]);
    }

    public function fast(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_time_ms' => $this->faker->numberBetween(50, 150),
            'status_code' => 200,
            'uptime_status' => true,
        ]);
    }

    public function slow(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_time_ms' => $this->faker->numberBetween(1000, 5000),
            'status_code' => 200,
            'uptime_status' => true,
        ]);
    }
}
