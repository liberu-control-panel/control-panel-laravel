<?php

namespace Database\Factories;

use App\Models\FtpAccount;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class FtpAccountFactory extends Factory
{
    protected $model = FtpAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'domain_id' => Domain::factory(),
            'username' => $this->faker->unique()->userName(),
            'password' => Hash::make('password'),
            'home_directory' => '/var/www/html',
            'quota_mb' => $this->faker->optional()->numberBetween(100, 10000),
            'bandwidth_limit_mb' => $this->faker->optional()->numberBetween(1000, 50000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withQuota(int $quotaMb): static
    {
        return $this->state(fn (array $attributes) => [
            'quota_mb' => $quotaMb,
        ]);
    }
}
