<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'name',
        'type',
        'frequency',
        'schedule_time',
        'destination_id',
        'is_active',
        'retention_days',
        'last_run_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_run_at'  => 'datetime',
    ];

    /** Backup type constants */
    const TYPE_FULL     = 'full';
    const TYPE_FILES    = 'files';
    const TYPE_DATABASE = 'database';
    const TYPE_EMAIL    = 'email';

    /** Frequency constants */
    const FREQUENCY_DAILY   = 'daily';
    const FREQUENCY_WEEKLY  = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';

    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_DAILY   => 'Daily',
            self::FREQUENCY_WEEKLY  => 'Weekly (Sunday)',
            self::FREQUENCY_MONTHLY => 'Monthly (1st)',
        ];
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_FULL     => 'Full Backup',
            self::TYPE_FILES    => 'Files Only',
            self::TYPE_DATABASE => 'Database Only',
            self::TYPE_EMAIL    => 'Email Only',
        ];
    }

    /**
     * Convert frequency + schedule_time to a cron expression.
     */
    public function toCronExpression(): string
    {
        [$hour, $minute] = explode(':', $this->schedule_time ?? '02:00');

        return match ($this->frequency) {
            self::FREQUENCY_WEEKLY  => "{$minute} {$hour} * * 0",
            self::FREQUENCY_MONTHLY => "{$minute} {$hour} 1 * *",
            default                 => "{$minute} {$hour} * * *",   // daily
        };
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BackupDestination::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
