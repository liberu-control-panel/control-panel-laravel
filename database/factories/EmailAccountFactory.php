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
            'forwarding_rules' => [],
            'autoresponder_enabled' => false,
            'spam_filter_enabled' => true,
            'spam_threshold' => 5,
            'spam_action' => 'move_to_spam',
            'keep_copy_on_server' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the email account has autoresponder enabled.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withAutoresponder()
    {
        return $this->state(function (array $attributes) {
            return [
                'autoresponder_enabled' => true,
                'autoresponder_subject' => 'Out of Office',
                'autoresponder_message' => 'I am currently out of office.',
                'autoresponder_start_date' => now(),
                'autoresponder_end_date' => now()->addDays(7),
            ];
        });
    }
}

