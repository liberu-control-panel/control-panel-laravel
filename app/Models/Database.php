<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Database extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'user_id',
        'name',
        'charset',
        'collation',
        'engine',
        'connection_type',
        'provider',
        'external_host',
        'external_port',
        'external_username',
        'external_password',
        'use_ssl',
        'ssl_ca',
        'ssl_cert',
        'ssl_key',
        'instance_identifier',
        'region',
        'size',
        'is_active'
    ];

    protected $casts = [
        'size' => 'integer',
        'external_port' => 'integer',
        'is_active' => 'boolean',
        'use_ssl' => 'boolean',
        'external_password' => 'encrypted',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Database engines
     */
    const ENGINE_MYSQL = 'mysql';
    const ENGINE_POSTGRESQL = 'postgresql';
    const ENGINE_MARIADB = 'mariadb';
    const ENGINE_REDIS = 'redis';

    /**
     * Connection types
     */
    const CONNECTION_SELF_HOSTED = 'self-hosted';
    const CONNECTION_MANAGED = 'managed';

    /**
     * Cloud providers
     */
    const PROVIDER_AWS = 'aws';
    const PROVIDER_AZURE = 'azure';
    const PROVIDER_DIGITALOCEAN = 'digitalocean';
    const PROVIDER_OVH = 'ovh';
    const PROVIDER_GCP = 'gcp';

    /**
     * Get the user that owns this database
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain that owns this database
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the database users
     */
    public function databaseUsers(): HasMany
    {
        return $this->hasMany(DatabaseUser::class);
    }

    /**
     * Get human readable database size
     */
    public function getHumanSizeAttribute(): string
    {
        if (!$this->size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get available database engines
     */
    public static function getEngines(): array
    {
        return [
            self::ENGINE_MYSQL => 'MySQL',
            self::ENGINE_POSTGRESQL => 'PostgreSQL',
            self::ENGINE_MARIADB => 'MariaDB'
        ];
    }

    /**
     * Get default charset for engine
     */
    public static function getDefaultCharset(string $engine): string
    {
        return match($engine) {
            self::ENGINE_MYSQL, self::ENGINE_MARIADB => 'utf8mb4',
            self::ENGINE_POSTGRESQL => 'UTF8',
            default => 'utf8mb4'
        };
    }

    /**
     * Get default collation for engine
     */
    public static function getDefaultCollation(string $engine): string
    {
        return match($engine) {
            self::ENGINE_MYSQL, self::ENGINE_MARIADB => 'utf8mb4_unicode_ci',
            self::ENGINE_POSTGRESQL => 'en_US.UTF-8',
            default => 'utf8mb4_unicode_ci'
        };
    }

    /**
     * Scope for active databases
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by engine
     */
    public function scopeByEngine($query, string $engine)
    {
        return $query->where('engine', $engine);
    }

    /**
     * Check if database is managed
     */
    public function isManaged(): bool
    {
        return $this->connection_type === self::CONNECTION_MANAGED;
    }

    /**
     * Check if database is self-hosted
     */
    public function isSelfHosted(): bool
    {
        return $this->connection_type === self::CONNECTION_SELF_HOSTED;
    }

    /**
     * Get available cloud providers
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_AWS => 'AWS (RDS/Aurora)',
            self::PROVIDER_AZURE => 'Azure Database',
            self::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
            self::PROVIDER_OVH => 'OVH',
            self::PROVIDER_GCP => 'Google Cloud SQL',
        ];
    }

    /**
     * Get connection types
     */
    public static function getConnectionTypes(): array
    {
        return [
            self::CONNECTION_SELF_HOSTED => 'Self-Hosted',
            self::CONNECTION_MANAGED => 'Managed Database',
        ];
    }

    /**
     * Scope for managed databases
     */
    public function scopeManaged($query)
    {
        return $query->where('connection_type', self::CONNECTION_MANAGED);
    }

    /**
     * Scope for self-hosted databases
     */
    public function scopeSelfHosted($query)
    {
        return $query->where('connection_type', self::CONNECTION_SELF_HOSTED);
    }
}