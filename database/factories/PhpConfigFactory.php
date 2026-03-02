<?php

namespace Database\Factories;

use App\Models\PhpConfig;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhpConfigFactory extends Factory
{
    protected $model = PhpConfig::class;

    public function definition(): array
    {
        return [
            'domain_id'           => Domain::factory(),
            'php_version'         => $this->faker->randomElement(PhpConfig::getSupportedVersions()),
            'memory_limit'        => $this->faker->randomElement([128, 256, 512]),
            'upload_max_filesize' => 64,
            'post_max_size'       => 64,
            'max_execution_time'  => 60,
            'max_input_time'      => 60,
            'max_input_vars'      => 1000,
            'display_errors'      => false,
            'short_open_tag'      => false,
            'error_reporting'     => 'E_ALL & ~E_DEPRECATED & ~E_STRICT',
            'session_save_path'   => null,
            'custom_settings'     => null,
        ];
    }
}
