<?php

namespace Database\Factories;

use App\Models\WordPressApplication;
use App\Models\Domain;
use App\Models\Database;
use Illuminate\Database\Eloquent\Factories\Factory;

class WordPressApplicationFactory extends Factory
{
    protected $model = WordPressApplication::class;

    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'database_id' => Database::factory(),
            'version' => '6.4.2',
            'php_version' => $this->faker->randomElement(['8.1', '8.2', '8.3', '8.4']),
            'admin_username' => 'admin',
            'admin_email' => $this->faker->safeEmail(),
            'admin_password' => bcrypt('password'),
            'site_title' => $this->faker->words(3, true),
            'site_url' => 'https://' . $this->faker->domainName(),
            'install_path' => '/public_html',
            'status' => 'pending',
            'installation_log' => null,
            'installed_at' => null,
            'last_update_check' => null,
        ];
    }

    public function installed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'installed',
            'installed_at' => now(),
            'installation_log' => 'Installation completed successfully',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'installation_log' => 'Installation failed: Connection error',
        ]);
    }
}
