<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\User;
use App\Models\Server;
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
        $domainName = $this->faker->unique()->domainName;

        return [
            'user_id' => User::factory(),
            'domain_name' => $domainName,
            'registration_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'expiration_date' => $this->faker->dateTimeBetween('now', '+2 years'),
            'server_id' => Server::factory(),
            'virtual_host' => $domainName,
            'letsencrypt_host' => $domainName,
            'letsencrypt_email' => $this->faker->safeEmail,
            'sftp_username' => $this->faker->userName,
            'sftp_password' => bcrypt('password'),
            'ssh_username' => $this->faker->userName,
            'ssh_password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the domain is expiring soon.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function expiringSoon()
    {
        return $this->state(function (array $attributes) {
            return [
                'expiration_date' => now()->addDays(rand(1, 30)),
            ];
        });
    }

    /**
     * Indicate that the domain is expired.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'expiration_date' => now()->subDays(rand(1, 30)),
            ];
        });
    }
}
