<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class SslCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'certificate_authority',
        'certificate',
        'private_key',
        'chain',
        'issued_at',
        'expires_at',
        'auto_renew',
        'status'
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $hidden = [
        'private_key'
    ];

    /**
     * Certificate authorities
     */
    const CA_LETSENCRYPT = 'letsencrypt';
    const CA_CUSTOM = 'custom';
    const CA_SELF_SIGNED = 'self_signed';

    /**
     * Certificate statuses
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';

    /**
     * Get the domain that owns this certificate
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Check if certificate is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if certificate expires soon (within 30 days)
     */
    public function expiresSoon(): bool
    {
        return $this->expires_at && $this->expires_at->diffInDays(now()) <= 30;
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        return max(0, $this->expires_at->diffInDays(now()));
    }

    /**
     * Get available certificate authorities
     */
    public static function getCertificateAuthorities(): array
    {
        return [
            self::CA_LETSENCRYPT => "Let's Encrypt",
            self::CA_CUSTOM => 'Custom Certificate',
            self::CA_SELF_SIGNED => 'Self-Signed'
        ];
    }

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_FAILED => 'Failed'
        ];
    }

    /**
     * Scope for active certificates
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for expiring certificates
     */
    public function scopeExpiring($query, int $days = 30)
    {
        return $query->where('expires_at', '<=', now()->addDays($days));
    }
}