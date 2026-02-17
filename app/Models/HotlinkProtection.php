<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotlinkProtection extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'enabled',
        'allowed_domains',
        'protected_extensions',
        'redirect_url',
        'allow_blank_referrer',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'allowed_domains' => 'array',
        'protected_extensions' => 'array',
        'allow_blank_referrer' => 'boolean',
    ];

    /**
     * Get the domain that owns the hotlink protection.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
