<?php

namespace Database\Factories;

use App\Models\SftpAccount;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class SftpAccountFactory extends Factory
{
    protected $model = SftpAccount::class;

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
            'ssh_key_auth_enabled' => false,
            'ssh_key_type' => 'rsa',
            'ssh_key_bits' => 4096,
        ];
    }

    public function withSshKeys(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssh_key_auth_enabled' => true,
            'ssh_public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQC...',
            'ssh_private_key' => encrypt('-----BEGIN OPENSSH PRIVATE KEY-----...'),
        ]);
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
