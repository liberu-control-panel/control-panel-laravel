<?php

namespace Database\Factories;

use App\Models\GitDeployment;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class GitDeploymentFactory extends Factory
{
    protected $model = GitDeployment::class;

    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'repository_url' => 'https://github.com/' . $this->faker->userName() . '/' . $this->faker->slug(2) . '.git',
            'repository_type' => $this->faker->randomElement(['github', 'gitlab', 'bitbucket', 'other']),
            'branch' => 'main',
            'deploy_path' => '/public_html',
            'deploy_key' => null,
            'webhook_secret' => null,
            'status' => 'pending',
            'deployment_log' => null,
            'build_command' => null,
            'deploy_command' => null,
            'auto_deploy' => false,
            'last_deployed_at' => null,
            'last_commit_hash' => null,
        ];
    }

    public function deployed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deployed',
            'last_deployed_at' => now(),
            'last_commit_hash' => bin2hex(random_bytes(20)),
            'deployment_log' => 'Deployment completed successfully',
        ]);
    }

    public function withAutoDeployment(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_deploy' => true,
            'webhook_secret' => bin2hex(random_bytes(20)),
        ]);
    }

    public function privateRepository(): static
    {
        return $this->state(fn (array $attributes) => [
            'deploy_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\n...\n-----END OPENSSH PRIVATE KEY-----",
        ]);
    }
}
