<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'repository_url',
        'repository_type',
        'branch',
        'deploy_path',
        'deploy_key',
        'webhook_secret',
        'status',
        'deployment_log',
        'build_command',
        'deploy_command',
        'auto_deploy',
        'last_deployed_at',
        'last_commit_hash',
    ];

    protected $casts = [
        'auto_deploy' => 'boolean',
        'last_deployed_at' => 'datetime',
    ];

    protected $hidden = [
        'deploy_key',
        'webhook_secret',
    ];

    /**
     * Get the domain that owns the git deployment
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Check if deployment is successful
     */
    public function isDeployed(): bool
    {
        return $this->status === 'deployed';
    }

    /**
     * Check if deployment is in progress
     */
    public function isDeploying(): bool
    {
        return in_array($this->status, ['cloning', 'updating']);
    }

    /**
     * Check if deployment failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if repository is private
     */
    public function isPrivate(): bool
    {
        return !empty($this->deploy_key);
    }

    /**
     * Get repository name from URL
     */
    public function getRepositoryNameAttribute(): string
    {
        $parts = explode('/', rtrim($this->repository_url, '/'));
        $name = end($parts);
        return str_replace('.git', '', $name);
    }

    /**
     * Get the full deployment path
     */
    public function getFullPathAttribute(): string
    {
        return rtrim($this->deploy_path, '/');
    }

    /**
     * Detect repository type from URL
     */
    public static function detectRepositoryType(string $url): string
    {
        if (str_contains($url, 'github.com')) {
            return 'github';
        } elseif (str_contains($url, 'gitlab.com')) {
            return 'gitlab';
        } elseif (str_contains($url, 'bitbucket.org')) {
            return 'bitbucket';
        }
        return 'other';
    }
}
