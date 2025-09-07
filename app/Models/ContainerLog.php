<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_id',
        'level',
        'message',
        'context',
        'logged_at'
    ];

    protected $casts = [
        'context' => 'array',
        'logged_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Get the container that owns this log
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /**
     * Get available log levels
     */
    public static function getLevels(): array
    {
        return [
            self::LEVEL_DEBUG => 'Debug',
            self::LEVEL_INFO => 'Info',
            self::LEVEL_WARNING => 'Warning',
            self::LEVEL_ERROR => 'Error',
            self::LEVEL_CRITICAL => 'Critical'
        ];
    }

    /**
     * Scope for error logs
     */
    public function scopeErrors($query)
    {
        return $query->whereIn('level', [self::LEVEL_ERROR, self::LEVEL_CRITICAL]);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('logged_at', '>=', now()->subHours($hours));
    }
}