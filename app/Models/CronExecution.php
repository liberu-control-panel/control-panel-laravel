<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'cron_job_id',
        'started_at',
        'finished_at',
        'exit_code',
        'output',
        'error_output',
        'duration'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the cron job that owns this execution
     */
    public function cronJob(): BelongsTo
    {
        return $this->belongsTo(CronJob::class);
    }

    /**
     * Check if execution was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->exit_code === 0;
    }

    /**
     * Check if execution failed
     */
    public function failed(): bool
    {
        return $this->exit_code !== 0;
    }

    /**
     * Get execution duration in seconds
     */
    public function getDurationInSecondsAttribute(): float
    {
        if ($this->started_at && $this->finished_at) {
            return $this->finished_at->diffInSeconds($this->started_at);
        }
        return 0;
    }

    /**
     * Scope for successful executions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('exit_code', 0);
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('exit_code', '!=', 0);
    }

    /**
     * Scope for recent executions
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }
}