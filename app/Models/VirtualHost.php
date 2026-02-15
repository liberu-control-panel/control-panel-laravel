<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualHost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_id',
        'server_id',
        'hostname',
        'document_root',
        'php_version',
        'ssl_enabled',
        'ssl_certificate_id',
        'letsencrypt_enabled',
        'nginx_config',
        'status',
        'port',
        'ipv4_address',
        'ipv6_address',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'letsencrypt_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING = 'pending';
    const STATUS_ERROR = 'error';

    /**
     * Get the user that owns the virtual host
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain associated with the virtual host
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the server where this virtual host is hosted
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the SSL certificate
     */
    public function sslCertificate(): BelongsTo
    {
        return $this->belongsTo(SslCertificate::class);
    }

    /**
     * Scope for active virtual hosts
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for SSL enabled virtual hosts
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
            '8.5' => 'PHP 8.5',
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
            self::STATUS_ERROR => 'Error',
        ];
    }
}
