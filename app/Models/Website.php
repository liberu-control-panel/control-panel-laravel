<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'description',
        'platform',
        'php_version',
        'database_type',
        'document_root',
        'status',
        'ssl_enabled',
        'auto_ssl',
        'uptime_percentage',
        'last_checked_at',
        'average_response_time',
        'monthly_bandwidth',
        'monthly_visitors',
        'disk_usage_mb',
        'server_id',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'auto_ssl' => 'boolean',
        'last_checked_at' => 'datetime',
        'uptime_percentage' => 'decimal:2',
        'average_response_time' => 'integer',
        'monthly_bandwidth' => 'integer',
        'monthly_visitors' => 'integer',
        'disk_usage_mb' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING = 'pending';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_ERROR = 'error';

    // Platform constants
    const PLATFORM_WORDPRESS = 'wordpress';
    const PLATFORM_LARAVEL = 'laravel';
    const PLATFORM_STATIC = 'static';
    const PLATFORM_NODEJS = 'nodejs';
    const PLATFORM_CUSTOM = 'custom';

    /**
     * Get the user that owns the website
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the server where this website is hosted
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the performance metrics for this website
     */
    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(WebsitePerformanceMetric::class);
    }

    /**
     * Scope for active websites
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for SSL enabled websites
     */
    public function scopeSslEnabled($query)
    {
        return $query->where('ssl_enabled', true);
    }

    /**
     * Get available PHP versions
     */
    public static function getPhpVersions(): array
    {
        return [
            '8.1' => 'PHP 8.1',
            '8.2' => 'PHP 8.2',
            '8.3' => 'PHP 8.3',
            '8.4' => 'PHP 8.4',
        ];
    }

    /**
     * Get available platforms
     */
    public static function getPlatforms(): array
    {
        return [
            self::PLATFORM_WORDPRESS => 'WordPress',
            self::PLATFORM_LARAVEL => 'Laravel',
            self::PLATFORM_STATIC => 'Static HTML',
            self::PLATFORM_NODEJS => 'Node.js',
            self::PLATFORM_CUSTOM => 'Custom',
        ];
    }

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_MAINTENANCE => 'Maintenance',
            self::STATUS_ERROR => 'Error',
        ];
    }

    /**
     * Get database types
     */
    public static function getDatabaseTypes(): array
    {
        return [
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'postgresql' => 'PostgreSQL',
            'sqlite' => 'SQLite',
            'none' => 'None',
        ];
    }

    /**
     * Get health status based on uptime and response time
     */
    public function getHealthStatus(): string
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return 'inactive';
        }

        if ($this->uptime_percentage >= 99.9) {
            return 'excellent';
        } elseif ($this->uptime_percentage >= 99.0) {
            return 'good';
        } elseif ($this->uptime_percentage >= 95.0) {
            return 'fair';
        } else {
            return 'poor';
        }
    }
}
