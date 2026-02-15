<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KubernetesNode extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_READY = 'Ready';
    const STATUS_NOT_READY = 'NotReady';
    const STATUS_UNKNOWN = 'Unknown';
    const STATUS_SCHEDULING_DISABLED = 'SchedulingDisabled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'server_id',
        'name',
        'uid',
        'kubernetes_version',
        'container_runtime',
        'os_image',
        'kernel_version',
        'architecture',
        'status',
        'schedulable',
        'labels',
        'annotations',
        'taints',
        'addresses',
        'capacity',
        'allocatable',
        'conditions',
        'last_heartbeat_time',
        'status_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'labels' => 'array',
        'annotations' => 'array',
        'taints' => 'array',
        'addresses' => 'array',
        'capacity' => 'array',
        'allocatable' => 'array',
        'conditions' => 'array',
        'schedulable' => 'boolean',
        'last_heartbeat_time' => 'datetime',
    ];

    /**
     * Get the server that owns this node.
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if node is ready.
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Check if node is schedulable.
     */
    public function isSchedulable(): bool
    {
        return $this->schedulable && $this->isReady();
    }

    /**
     * Check if node has a specific label.
     */
    public function hasLabel(string $key, ?string $value = null): bool
    {
        if (!$this->labels) {
            return false;
        }

        if ($value === null) {
            return isset($this->labels[$key]);
        }

        return isset($this->labels[$key]) && $this->labels[$key] === $value;
    }

    /**
     * Check if node has a specific taint.
     */
    public function hasTaint(string $key, ?string $effect = null): bool
    {
        if (!$this->taints) {
            return false;
        }

        foreach ($this->taints as $taint) {
            if ($taint['key'] === $key) {
                if ($effect === null || $taint['effect'] === $effect) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get node role from labels.
     */
    public function getRole(): string
    {
        if ($this->hasLabel('node-role.kubernetes.io/master') || 
            $this->hasLabel('node-role.kubernetes.io/control-plane')) {
            return 'master';
        }

        if ($this->hasLabel('node-role.kubernetes.io/worker')) {
            return 'worker';
        }

        return 'worker'; // Default to worker
    }

    /**
     * Get CPU capacity in cores.
     */
    public function getCpuCapacity(): ?float
    {
        if (!$this->capacity || !isset($this->capacity['cpu'])) {
            return null;
        }

        return $this->parseResourceQuantity($this->capacity['cpu']);
    }

    /**
     * Get memory capacity in GB.
     */
    public function getMemoryCapacity(): ?float
    {
        if (!$this->capacity || !isset($this->capacity['memory'])) {
            return null;
        }

        return $this->parseMemoryQuantity($this->capacity['memory']);
    }

    /**
     * Get allocatable CPU in cores.
     */
    public function getAllocatableCpu(): ?float
    {
        if (!$this->allocatable || !isset($this->allocatable['cpu'])) {
            return null;
        }

        return $this->parseResourceQuantity($this->allocatable['cpu']);
    }

    /**
     * Get allocatable memory in GB.
     */
    public function getAllocatableMemory(): ?float
    {
        if (!$this->allocatable || !isset($this->allocatable['memory'])) {
            return null;
        }

        return $this->parseMemoryQuantity($this->allocatable['memory']);
    }

    /**
     * Parse Kubernetes resource quantity (e.g., "4", "4000m" = 4 cores).
     */
    protected function parseResourceQuantity(string $quantity): float
    {
        if (str_ends_with($quantity, 'm')) {
            return (float) substr($quantity, 0, -1) / 1000;
        }

        return (float) $quantity;
    }

    /**
     * Parse Kubernetes memory quantity to GB.
     */
    protected function parseMemoryQuantity(string $quantity): float
    {
        $units = [
            'Ki' => 1024,
            'Mi' => 1024 * 1024,
            'Gi' => 1024 * 1024 * 1024,
            'Ti' => 1024 * 1024 * 1024 * 1024,
            'K' => 1000,
            'M' => 1000 * 1000,
            'G' => 1000 * 1000 * 1000,
            'T' => 1000 * 1000 * 1000 * 1000,
        ];

        foreach ($units as $unit => $multiplier) {
            if (str_ends_with($quantity, $unit)) {
                $value = (float) substr($quantity, 0, -strlen($unit));
                return ($value * $multiplier) / (1024 * 1024 * 1024); // Convert to GB
            }
        }

        // If no unit, assume bytes
        return (float) $quantity / (1024 * 1024 * 1024);
    }

    /**
     * Get available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_READY => 'Ready',
            self::STATUS_NOT_READY => 'Not Ready',
            self::STATUS_UNKNOWN => 'Unknown',
            self::STATUS_SCHEDULING_DISABLED => 'Scheduling Disabled',
        ];
    }

    /**
     * Scope a query to only include ready nodes.
     */
    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    /**
     * Scope a query to only include schedulable nodes.
     */
    public function scopeSchedulable($query)
    {
        return $query->where('schedulable', true)->where('status', self::STATUS_READY);
    }

    /**
     * Scope a query to only include worker nodes.
     */
    public function scopeWorkers($query)
    {
        return $query->whereJsonDoesntContain('labels->node-role.kubernetes.io/master', true)
                     ->whereJsonDoesntContain('labels->node-role.kubernetes.io/control-plane', true);
    }
}
