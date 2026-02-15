<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelmRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'release_name',
        'chart_name',
        'chart_version',
        'namespace',
        'status',
        'values',
        'notes',
        'installed_at',
        'updated_at',
    ];

    protected $casts = [
        'values' => 'array',
        'installed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the server that owns the release
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if release is deployed
     */
    public function isDeployed(): bool
    {
        return $this->status === 'deployed';
    }

    /**
     * Check if release has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->release_name} ({$this->chart_name})";
    }
}
