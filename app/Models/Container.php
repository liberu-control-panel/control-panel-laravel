<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Container extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'name',
        'type',
        'image',
        'container_name',
        'status',
        'ports',
        'environment',
        'volumes',
        'cpu_limit',
        'memory_limit',
        'restart_policy'
    ];

    protected $casts = [
        'ports' => 'array',
        'environment' => 'array',
        'volumes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Container types
     */
    const TYPE_WEB = 'web';
    const TYPE_PHP = 'php';
    const TYPE_DATABASE = 'database';
    const TYPE_FILEMANAGER = 'filemanager';
    const TYPE_FTP = 'ftp';
    const TYPE_CRON = 'cron';
    const TYPE_DB_ADMIN = 'db_admin';
    const TYPE_REDIS = 'redis';
    const TYPE_MEMCACHED = 'memcached';

    /**
     * Container statuses
     */
    const STATUS_RUNNING = 'running';
    const STATUS_STOPPED = 'stopped';
    const STATUS_RESTARTING = 'restarting';
    const STATUS_PAUSED = 'paused';
    const STATUS_EXITED = 'exited';
    const STATUS_DEAD = 'dead';

    /**
     * Get the domain that owns this container
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get container logs
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ContainerLog::class);
    }

    /**
     * Check if container is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if container is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === self::STATUS_STOPPED;
    }

    /**
     * Get container resource usage
     */
    public function getResourceUsage(): array
    {
        // This would integrate with Docker stats API
        return [
            'cpu_percent' => 0,
            'memory_usage' => 0,
            'memory_limit' => $this->memory_limit,
            'network_io' => ['rx' => 0, 'tx' => 0],
            'block_io' => ['read' => 0, 'write' => 0]
        ];
    }

    /**
     * Get available container types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_WEB => 'Web Server',
            self::TYPE_PHP => 'PHP-FPM',
            self::TYPE_DATABASE => 'Database',
            self::TYPE_FILEMANAGER => 'File Manager',
            self::TYPE_FTP => 'FTP Server',
            self::TYPE_CRON => 'Cron Jobs',
            self::TYPE_DB_ADMIN => 'Database Admin',
            self::TYPE_REDIS => 'Redis Cache',
            self::TYPE_MEMCACHED => 'Memcached'
        ];
    }

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_RUNNING => 'Running',
            self::STATUS_STOPPED => 'Stopped',
            self::STATUS_RESTARTING => 'Restarting',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_EXITED => 'Exited',
            self::STATUS_DEAD => 'Dead'
        ];
    }

    /**
     * Scope for running containers
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope for stopped containers
     */
    public function scopeStopped($query)
    {
        return $query->where('status', self::STATUS_STOPPED);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}