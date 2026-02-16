<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContainerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Container::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'domain_id' => Domain::factory(),
            'name' => 'web-' . $this->faker->slug(2),
            'type' => Container::TYPE_WEB,
            'image' => 'nginx:alpine',
            'container_name' => 'web-' . $this->faker->slug(2) . '-' . substr(md5(time()), 0, 8),
            'status' => Container::STATUS_RUNNING,
            'ports' => [
                ['host' => $this->faker->numberBetween(8000, 9000), 'container' => 80, 'protocol' => 'tcp'],
            ],
            'environment' => [
                'DOMAIN_NAME' => $this->faker->domainName,
            ],
            'volumes' => [
                '/var/www/html:/var/www/html',
            ],
            'cpu_limit' => '1000m',
            'memory_limit' => '512Mi',
            'restart_policy' => 'unless-stopped',
        ];
    }

    /**
     * Indicate that the container is stopped.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function stopped()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Container::STATUS_STOPPED,
            ];
        });
    }

    /**
     * Indicate that the container is for PHP-FPM.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function php()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Container::TYPE_PHP,
                'image' => 'php:8.1-fpm-alpine',
            ];
        });
    }

    /**
     * Indicate that the container is for database.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function database()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Container::TYPE_DATABASE,
                'image' => 'mysql:8.0',
            ];
        });
    }
}
