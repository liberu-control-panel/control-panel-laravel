<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Domain::class;

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
            'domain_name' => $this->faker->unique()->domainName,
            'registration_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'expiration_date' => $this->faker->dateTimeBetween('now', '+5 years'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

