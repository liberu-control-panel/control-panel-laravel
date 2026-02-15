<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaravelApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'database_id',
        'repository_slug',
        'repository_name',
        'repository_url',
        'version',
        'php_version',
        'install_path',
        'app_url',
        'status',
        'installation_log',
        'installed_at',
        'last_update_check',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'last_update_check' => 'datetime',
    ];

    /**
     * Get the domain that owns the Laravel application
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the database for the Laravel application
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    /**
     * Check if application is installed
     */
    public function isInstalled(): bool
    {
        return $this->status === 'installed';
    }

    /**
     * Check if application is currently installing
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
     * Check if application is updating
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

    /**
     * Get the GitHub repository URL
     */
    public function getGithubUrlAttribute(): string
    {
        return config('repositories.github_base_url') . '/' . $this->repository_url;
    }

    /**
     * Get the repository configuration
     */
    public function getRepositoryConfigAttribute(): ?array
    {
        $repositories = config('repositories.repositories', []);
        
        foreach ($repositories as $repo) {
            if ($repo['slug'] === $this->repository_slug) {
                return $repo;
            }
        }
        
        return null;
    }
}
