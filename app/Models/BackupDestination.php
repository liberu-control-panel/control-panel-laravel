<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'is_default',
        'is_active',
        'configuration',
        'description',
        'retention_days',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'configuration' => 'array',
        'retention_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Destination types
     */
    const TYPE_LOCAL = 'local';
    const TYPE_SFTP = 'sftp';
    const TYPE_FTP = 'ftp';
    const TYPE_S3 = 's3';

    /**
     * Get all backups for this destination
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'destination_id');
    }

    /**
     * Get available destination types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_LOCAL => 'Local Storage',
            self::TYPE_SFTP => 'SFTP',
            self::TYPE_FTP => 'FTP',
            self::TYPE_S3 => 'S3 (Amazon S3, MinIO, etc.)',
        ];
    }

    /**
     * Check if this is the default destination
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Check if this destination is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope for active destinations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default destination
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get configuration value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
    }

    /**
     * Set configuration value
     */
    public function setConfigValue(string $key, $value): void
    {
        $config = $this->configuration ?? [];
        $config[$key] = $value;
        $this->configuration = $config;
    }

    /**
     * Validate configuration based on type
     */
    public function validateConfiguration(): bool
    {
        $config = $this->configuration;

        return match ($this->type) {
            self::TYPE_LOCAL => isset($config['path']),
            self::TYPE_SFTP => isset($config['host'], $config['port'], $config['username']),
            self::TYPE_FTP => isset($config['host'], $config['port'], $config['username']),
            self::TYPE_S3 => isset($config['key'], $config['secret'], $config['region'], $config['bucket']),
            default => false,
        };
    }

    /**
     * Get filesystem disk name for this destination
     */
    public function getDiskName(): string
    {
        return "backup_destination_{$this->id}";
    }
}
