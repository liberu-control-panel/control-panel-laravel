<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_KUBERNETES = 'kubernetes';
    const TYPE_DOCKER = 'docker';
    const TYPE_STANDALONE = 'standalone';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_ERROR = 'error';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'hostname',
        'port',
        'ip_address',
        'type',
        'deployment_mode',
        'cloud_provider',
        'auto_scaling_enabled',
        'status',
        'description',
        'metadata',
        'is_default',
        'max_domains',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'is_default' => 'boolean',
        'auto_scaling_enabled' => 'boolean',
        'max_domains' => 'integer',
        'port' => 'integer',
    ];

    /**
     * Get the credentials for this server.
     */
    public function credentials()
    {
        return $this->hasMany(ServerCredential::class);
    }

    /**
     * Get the active credential for this server.
     */
    public function activeCredential()
    {
        return $this->hasOne(ServerCredential::class)->where('is_active', true);
    }

    /**
     * Get the domains hosted on this server.
     */
    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Get the Kubernetes nodes for this server.
     */
    public function kubernetesNodes()
    {
        return $this->hasMany(KubernetesNode::class);
    }

    /**
     * Check if server is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if server is a Kubernetes server.
     */
    public function isKubernetes(): bool
    {
        return $this->type === self::TYPE_KUBERNETES;
    }

    /**
     * Check if server can accept more domains.
     */
    public function canAcceptDomains(): bool
    {
        return $this->isActive() && $this->domains()->count() < $this->max_domains;
    }

    /**
     * Get available server types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_KUBERNETES => 'Kubernetes',
            self::TYPE_DOCKER => 'Docker',
            self::TYPE_STANDALONE => 'Standalone',
        ];
    }

    /**
     * Get available server statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_MAINTENANCE => 'Maintenance',
            self::STATUS_ERROR => 'Error',
        ];
    }

    /**
     * Scope a query to only include active servers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include Kubernetes servers.
     */
    public function scopeKubernetes($query)
    {
        return $query->where('type', self::TYPE_KUBERNETES);
    }

    /**
     * Get the default server.
     */
    public static function getDefault()
    {
        return self::where('is_default', true)->active()->first();
    }

    /**
     * Check if server supports auto-scaling
     */
    public function supportsAutoScaling(): bool
    {
        return $this->isKubernetes() && !empty($this->cloud_provider);
    }

    /**
     * Check if auto-scaling is enabled for this server
     */
    public function isAutoScalingEnabled(): bool
    {
        return $this->auto_scaling_enabled && $this->supportsAutoScaling();
    }

    /**
     * Check if server is Docker type
     */
    public function isDocker(): bool
    {
        return $this->type === self::TYPE_DOCKER;
    }

    /**
     * Check if server is Standalone type
     */
    public function isStandalone(): bool
    {
        return $this->type === self::TYPE_STANDALONE;
    }
}
