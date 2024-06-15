<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailAccount::class;

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
            'domain_id' => function () {
                return \App\Models\Domain::factory()->create()->id;
            },
            'email_address' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'), // Replace 'password' with your desired default password generation logic
            'quota' => $this->faker->numberBetween(100, 5000), // Quota between 100 MB to 5000 MB
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

