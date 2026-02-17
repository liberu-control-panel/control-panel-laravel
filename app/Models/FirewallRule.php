<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirewallRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'action',
        'ip_address',
        'protocol',
        'port',
        'port_range',
        'description',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'port' => 'integer',
    ];

    /**
     * Get the user that owns the firewall rule.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validate IP address format (supports CIDR notation)
     */
    public function isValidIpAddress(): bool
    {
        if (filter_var($this->ip_address, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check CIDR notation
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $this->ip_address)) {
            return true;
        }

        return false;
    }
}
