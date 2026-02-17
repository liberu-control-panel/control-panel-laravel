<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectoryProtection extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'directory_path',
        'auth_name',
        'htpasswd_file_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the domain that owns the directory protection.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the users for this directory protection.
     */
    public function users()
    {
        return $this->hasMany(DirectoryProtectionUser::class);
    }
}

class DirectoryProtectionUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'directory_protection_id',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the directory protection that owns the user.
     */
    public function directoryProtection()
    {
        return $this->belongsTo(DirectoryProtection::class);
    }
}
