<?php

namespace Tests\Feature;

use App\Models\BackupSchedule;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->domain = Domain::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_backup_schedule_can_be_created(): void
    {
        $schedule = BackupSchedule::factory()->create([
            'domain_id'     => $this->domain->id,
            'name'          => 'Nightly backup',
            'frequency'     => BackupSchedule::FREQUENCY_DAILY,
            'schedule_time' => '02:00',
        ]);

        $this->assertDatabaseHas('backup_schedules', [
            'domain_id' => $this->domain->id,
            'name'      => 'Nightly backup',
            'frequency' => BackupSchedule::FREQUENCY_DAILY,
        ]);

        $this->assertTrue($schedule->is_active);
    }

    public function test_backup_schedule_generates_correct_cron_expression(): void
    {
        $daily = BackupSchedule::factory()->make([
            'frequency'     => BackupSchedule::FREQUENCY_DAILY,
            'schedule_time' => '03:30',
        ]);
        $this->assertSame('30 3 * * *', $daily->toCronExpression());

        $weekly = BackupSchedule::factory()->make([
            'frequency'     => BackupSchedule::FREQUENCY_WEEKLY,
            'schedule_time' => '01:00',
        ]);
        $this->assertSame('0 1 * * 0', $weekly->toCronExpression());

        $monthly = BackupSchedule::factory()->make([
            'frequency'     => BackupSchedule::FREQUENCY_MONTHLY,
            'schedule_time' => '00:00',
        ]);
        $this->assertSame('0 0 1 * *', $monthly->toCronExpression());
    }

    public function test_backup_schedule_belongs_to_domain(): void
    {
        $schedule = BackupSchedule::factory()->create(['domain_id' => $this->domain->id]);

        $this->assertTrue($schedule->domain->is($this->domain));
    }

    public function test_domain_has_backup_schedules_relationship(): void
    {
        BackupSchedule::factory()->count(3)->create(['domain_id' => $this->domain->id]);

        $this->assertCount(3, $this->domain->backupSchedules);
    }

    public function test_active_scope_returns_only_active_schedules(): void
    {
        BackupSchedule::factory()->create(['domain_id' => $this->domain->id, 'is_active' => true]);
        BackupSchedule::factory()->create(['domain_id' => $this->domain->id, 'is_active' => false]);

        $active = BackupSchedule::active()->where('domain_id', $this->domain->id)->get();

        $this->assertCount(1, $active);
    }

    public function test_get_frequencies_returns_expected_values(): void
    {
        $frequencies = BackupSchedule::getFrequencies();

        $this->assertArrayHasKey(BackupSchedule::FREQUENCY_DAILY,   $frequencies);
        $this->assertArrayHasKey(BackupSchedule::FREQUENCY_WEEKLY,  $frequencies);
        $this->assertArrayHasKey(BackupSchedule::FREQUENCY_MONTHLY, $frequencies);
    }
}
