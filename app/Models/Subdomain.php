<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subdomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'subdomain',
        'document_root',
        'php_version',
        'is_active',
        'redirect_url',
        'redirect_type'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Redirect types
     */
    const REDIRECT_301 = '301';
    const REDIRECT_302 = '302';

    /**
     * Get the domain that owns this subdomain
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the full subdomain name
     */
    public function getFullNameAttribute(): string
    {
        return $this->subdomain . '.' . $this->domain->domain_name;
    }

    /**
     * Get available redirect types
     */
    public static function getRedirectTypes(): array
    {
        return [
            self::REDIRECT_301 => 'Permanent (301)',
            self::REDIRECT_302 => 'Temporary (302)'
        ];
    }

    /**
     * Scope for active subdomains
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}