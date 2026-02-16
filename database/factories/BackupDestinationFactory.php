<?php

namespace Database\Factories;

use App\Models\BackupDestination;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackupDestinationFactory extends Factory
{
    protected $model = BackupDestination::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement([
                BackupDestination::TYPE_LOCAL,
                BackupDestination::TYPE_SFTP,
                BackupDestination::TYPE_FTP,
                BackupDestination::TYPE_S3,
            ]),
            'is_default' => false,
            'is_active' => true,
            'configuration' => $this->getConfiguration(),
            'description' => $this->faker->sentence(),
            'retention_days' => $this->faker->randomElement([7, 14, 30, 60, 90]),
        ];
    }

    protected function getConfiguration(): array
    {
        return [
            'path' => storage_path('app/backups'),
        ];
    }

    public function local(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => BackupDestination::TYPE_LOCAL,
            'configuration' => [
                'path' => storage_path('app/backups'),
            ],
        ]);
    }

    public function sftp(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => BackupDestination::TYPE_SFTP,
            'configuration' => [
                'host' => $this->faker->domainName(),
                'port' => 22,
                'username' => $this->faker->userName(),
                'password' => $this->faker->password(),
                'root' => '/backups',
            ],
        ]);
    }

    public function ftp(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => BackupDestination::TYPE_FTP,
            'configuration' => [
                'host' => $this->faker->domainName(),
                'port' => 21,
                'username' => $this->faker->userName(),
                'password' => $this->faker->password(),
                'root' => '/backups',
                'passive' => true,
                'ssl' => false,
            ],
        ]);
    }

    public function s3(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => BackupDestination::TYPE_S3,
            'configuration' => [
                'key' => $this->faker->uuid(),
                'secret' => $this->faker->sha256(),
                'region' => 'us-east-1',
                'bucket' => 'backup-' . $this->faker->slug(),
            ],
        ]);
    }

    public function default(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
