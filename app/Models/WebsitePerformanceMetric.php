<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'response_time_ms',
        'status_code',
        'uptime_status',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'bandwidth_used',
        'visitors_count',
        'checked_at',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'status_code' => 'integer',
        'uptime_status' => 'boolean',
        'cpu_usage' => 'decimal:2',
        'memory_usage' => 'decimal:2',
        'disk_usage' => 'decimal:2',
        'bandwidth_used' => 'integer',
        'visitors_count' => 'integer',
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the website that owns this metric
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * Scope for successful checks
     */
    public function scopeSuccessful($query)
    {
        return $query->where('uptime_status', true);
    }

    /**
     * Scope for failed checks
     */
    public function scopeFailed($query)
    {
        return $query->where('uptime_status', false);
    }

    /**
     * Scope for recent metrics
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('checked_at', '>=', now()->subHours($hours));
    }
}
