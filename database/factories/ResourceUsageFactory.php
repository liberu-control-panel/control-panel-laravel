<?php

namespace Database\Factories;

use App\Models\ResourceUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceUsageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ResourceUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => function () {
                return \App\Models\User::factory()->create()->id;
            },
            'disk_usage' => $this->faker->numberBetween(100, 5000), // Disk usage between 100 MB to 5000 MB
            'bandwidth_usage' => $this->faker->numberBetween(100, 10000), // Bandwidth usage between 100 MB to 10,000 MB
            'month' => $this->faker->numberBetween(1, 12), // Random month between 1 (January) to 12 (December)
            'year' => $this->faker->numberBetween(2020, 2023), // Random year between 2020 to 2023
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

