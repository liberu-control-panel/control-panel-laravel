<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'name',
        'command',
        'schedule',
        'is_active',
        'last_run_at',
        'next_run_at',
        'output',
        'error_output'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Common cron schedules
     */
    const SCHEDULE_EVERY_MINUTE = '* * * * *';
    const SCHEDULE_HOURLY = '0 * * * *';
    const SCHEDULE_DAILY = '0 0 * * *';
    const SCHEDULE_WEEKLY = '0 0 * * 0';
    const SCHEDULE_MONTHLY = '0 0 1 * *';

    /**
     * Get the domain that owns this cron job
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the execution logs for this cron job
     */
    public function executions(): HasMany
    {
        return $this->hasMany(CronExecution::class);
    }

    /**
     * Get common schedules
     */
    public static function getCommonSchedules(): array
    {
        return [
            self::SCHEDULE_EVERY_MINUTE => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            self::SCHEDULE_HOURLY => 'Every hour',
            '0 */6 * * *' => 'Every 6 hours',
            '0 */12 * * *' => 'Every 12 hours',
            self::SCHEDULE_DAILY => 'Daily at midnight',
            '0 6 * * *' => 'Daily at 6 AM',
            '0 12 * * *' => 'Daily at noon',
            '0 18 * * *' => 'Daily at 6 PM',
            self::SCHEDULE_WEEKLY => 'Weekly on Sunday',
            self::SCHEDULE_MONTHLY => 'Monthly on 1st'
        ];
    }

    /**
     * Parse cron schedule to human readable format
     */
    public function getHumanScheduleAttribute(): string
    {
        $schedules = self::getCommonSchedules();
        return $schedules[$this->schedule] ?? $this->schedule;
    }

    /**
     * Scope for active cron jobs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for jobs that should run now
     */
    public function scopeDue($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('next_run_at')
                          ->orWhere('next_run_at', '<=', now());
                    });
    }
}