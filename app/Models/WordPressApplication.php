<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'database_id',
        'version',
        'php_version',
        'admin_username',
        'admin_email',
        'admin_password',
        'site_title',
        'site_url',
        'install_path',
        'status',
        'installation_log',
        'installed_at',
        'last_update_check',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'last_update_check' => 'datetime',
    ];

    protected $hidden = [
        'admin_password',
    ];

    /**
     * Get the domain that owns the WordPress application
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the database for the WordPress application
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    /**
     * Check if WordPress is installed
     */
    public function isInstalled(): bool
    {
        return $this->status === 'installed';
    }

    /**
     * Check if WordPress is currently installing
     */
    public function isInstalling(): bool
    {
        return $this->status === 'installing';
    }

    /**
     * Check if installation failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if WordPress is updating
     */
    public function isUpdating(): bool
    {
        return $this->status === 'updating';
    }

    /**
     * Get the full installation path
     */
    public function getFullPathAttribute(): string
    {
        return rtrim($this->install_path, '/');
    }
}
