<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SftpAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_id',
        'username',
        'password',
        'home_directory',
        'quota_mb',
        'bandwidth_limit_mb',
        'is_active',
        'last_login_at',
        'ssh_key_auth_enabled',
        'ssh_public_key',
        'ssh_private_key',
        'ssh_key_type',
        'ssh_key_bits',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'quota_mb' => 'integer',
        'bandwidth_limit_mb' => 'integer',
        'ssh_key_auth_enabled' => 'boolean',
        'ssh_key_bits' => 'integer',
    ];

    protected $hidden = [
        'password',
        'ssh_private_key',
    ];

    /**
     * Get the user that owns the SFTP account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain associated with the SFTP account.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Check if using SSH key authentication
     */
    public function usingSshKeys(): bool
    {
        return $this->ssh_key_auth_enabled && !empty($this->ssh_public_key);
    }

    /**
     * Check if using password authentication
     */
    public function usingPassword(): bool
    {
        return !empty($this->password);
    }
}
