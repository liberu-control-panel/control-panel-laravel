<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FtpAccount extends Model
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'quota_mb' => 'integer',
        'bandwidth_limit_mb' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the user that owns the FTP account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domain associated with the FTP account.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
