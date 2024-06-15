<?php

namespace Database\Factories;

use App\Models\HostingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class HostingPlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = HostingPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true), // Generates a name consisting of 3 words
            'description' => $this->faker->paragraph(),
            'disk_space' => $this->faker->numberBetween(100, 10000), // Generates disk space between 100 MB to 10,000 MB
            'bandwidth' => $this->faker->numberBetween(100, 10000), // Generates bandwidth between 100 MB to 10,000 MB
            'price' => $this->faker->randomFloat(2, 10, 200), // Generates a price between 10 and 200 with up to 2 decimal places
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

