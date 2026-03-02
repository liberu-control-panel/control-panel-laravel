<?php

namespace Database\Factories;

use App\Models\BackupSchedule;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackupScheduleFactory extends Factory
{
    protected $model = BackupSchedule::class;

    public function definition(): array
    {
        return [
            'domain_id'     => Domain::factory(),
            'name'          => $this->faker->words(3, true) . ' backup',
            'type'          => $this->faker->randomElement([
                BackupSchedule::TYPE_FULL,
                BackupSchedule::TYPE_FILES,
                BackupSchedule::TYPE_DATABASE,
            ]),
            'frequency'     => $this->faker->randomElement([
                BackupSchedule::FREQUENCY_DAILY,
                BackupSchedule::FREQUENCY_WEEKLY,
                BackupSchedule::FREQUENCY_MONTHLY,
            ]),
            'schedule_time' => '02:00',
            'destination_id' => null,
            'is_active'      => true,
            'retention_days' => 30,
            'last_run_at'    => null,
        ];
    }
}
