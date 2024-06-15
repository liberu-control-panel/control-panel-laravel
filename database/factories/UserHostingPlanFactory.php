<?php

namespace Database\Factories;

use App\Models\UserHostingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserHostingPlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserHostingPlan::class;

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
            'hosting_plan_id' => function () {
                return \App\Models\HostingPlan::factory()->create()->id;
            },
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'end_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

