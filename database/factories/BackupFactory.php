<?php

namespace Database\Factories;

use App\Models\Backup;
use App\Models\Domain;
use App\Models\BackupDestination;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'destination_id' => null,
            'type' => $this->faker->randomElement([
                Backup::TYPE_FULL,
                Backup::TYPE_FILES,
                Backup::TYPE_DATABASE,
                Backup::TYPE_EMAIL,
            ]),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'file_path' => storage_path('app/backups/test_backup_' . $this->faker->uuid() . '.tar.gz'),
            'file_size' => $this->faker->numberBetween(1000000, 1000000000),
            'status' => Backup::STATUS_COMPLETED,
            'started_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            'completed_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'error_message' => null,
            'is_automated' => $this->faker->boolean(30),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function running(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_RUNNING,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_COMPLETED,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => Backup::STATUS_FAILED,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    public function full(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => Backup::TYPE_FULL,
        ]);
    }

    public function filesOnly(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => Backup::TYPE_FILES,
        ]);
    }

    public function databaseOnly(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => Backup::TYPE_DATABASE,
        ]);
    }

    public function automated(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_automated' => true,
        ]);
    }

    public function withDestination(): self
    {
        return $this->state(fn (array $attributes) => [
            'destination_id' => BackupDestination::factory(),
        ]);
    }
}
