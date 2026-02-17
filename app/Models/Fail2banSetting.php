<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fail2banSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'jail_name',
        'enabled',
        'max_retry',
        'find_time',
        'ban_time',
        'whitelist_ips',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'max_retry' => 'integer',
        'find_time' => 'integer',
        'ban_time' => 'integer',
        'whitelist_ips' => 'array',
    ];

    /**
     * Get the user that owns the fail2ban setting.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bans for this jail.
     */
    public function bans()
    {
        return $this->hasMany(Fail2banBan::class, 'jail_name', 'jail_name');
    }
}

class Fail2banBan extends Model
{
    use HasFactory;

    protected $fillable = [
        'jail_name',
        'ip_address',
        'banned_at',
        'unbanned_at',
        'ban_count',
        'reason',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'unbanned_at' => 'datetime',
        'ban_count' => 'integer',
    ];

    /**
     * Check if the ban is currently active.
     */
    public function isActive(): bool
    {
        return is_null($this->unbanned_at) || $this->unbanned_at->isFuture();
    }
}
