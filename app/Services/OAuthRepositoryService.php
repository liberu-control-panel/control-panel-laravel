<?php

namespace App\Services;

use App\Models\ConnectedAccount;
use App\Models\GitDeployment;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Exception;

class OAuthRepositoryService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Get list of repositories from OAuth provider
     */
    public function getRepositories(ConnectedAccount $account, int $page = 1, int $perPage = 30): array
    {
        try {
            switch ($account->provider) {
                case 'github':
                    return $this->getGitHubRepositories($account, $page, $perPage);
                case 'gitlab':
                    return $this->getGitLabRepositories($account, $page, $perPage);
                default:
                    throw new Exception("Unsupported OAuth provider: {$account->provider}");
            }
        } catch (Exception $e) {
            Log::error("Failed to fetch repositories from {$account->provider}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get GitHub repositories
     */
    protected function getGitHubRepositories(ConnectedAccount $account, int $page, int $perPage): array
    {
        $response = $this->client->get('https://api.github.com/user/repos', [
            'headers' => [
                'Authorization' => "Bearer {$account->token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
                'sort' => 'updated',
                'affiliation' => 'owner,collaborator',
            ],
        ]);

        $repos = json_decode($response->getBody()->getContents(), true);
        
        return array_map(function ($repo) {
            return [
                'id' => $repo['id'],
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'description' => $repo['description'] ?? '',
                'clone_url' => $repo['clone_url'],
                'ssh_url' => $repo['ssh_url'],
                'default_branch' => $repo['default_branch'],
                'private' => $repo['private'],
                'updated_at' => $repo['updated_at'],
            ];
        }, $repos);
    }

    /**
     * Get GitLab repositories
     */
    protected function getGitLabRepositories(ConnectedAccount $account, int $page, int $perPage): array
    {
        $gitlabUrl = config('services.gitlab.instance_uri', 'https://gitlab.com');
        
        $response = $this->client->get("{$gitlabUrl}/api/v4/projects", [
            'headers' => [
                'Authorization' => "Bearer {$account->token}",
            ],
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
                'membership' => true,
                'order_by' => 'updated_at',
            ],
        ]);

        $projects = json_decode($response->getBody()->getContents(), true);
        
        return array_map(function ($project) {
            return [
                'id' => $project['id'],
                'name' => $project['name'],
                'full_name' => $project['path_with_namespace'],
                'description' => $project['description'] ?? '',
                'clone_url' => $project['http_url_to_repo'],
                'ssh_url' => $project['ssh_url_to_repo'],
                'default_branch' => $project['default_branch'] ?? 'main',
                'private' => !$project['public'],
                'updated_at' => $project['last_activity_at'],
            ];
        }, $projects);
    }

    /**
     * Get repository branches
     */
    public function getRepositoryBranches(ConnectedAccount $account, string $repoFullName): array
    {
        try {
            switch ($account->provider) {
                case 'github':
                    return $this->getGitHubBranches($account, $repoFullName);
                case 'gitlab':
                    return $this->getGitLabBranches($account, $repoFullName);
                default:
                    throw new Exception("Unsupported OAuth provider: {$account->provider}");
            }
        } catch (Exception $e) {
            Log::error("Failed to fetch branches from {$account->provider}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get GitHub repository branches
     */
    protected function getGitHubBranches(ConnectedAccount $account, string $repoFullName): array
    {
        $response = $this->client->get("https://api.github.com/repos/{$repoFullName}/branches", [
            'headers' => [
                'Authorization' => "Bearer {$account->token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        $branches = json_decode($response->getBody()->getContents(), true);
        
        return array_map(fn($branch) => $branch['name'], $branches);
    }

    /**
     * Get GitLab repository branches
     */
    protected function getGitLabBranches(ConnectedAccount $account, string $projectId): array
    {
        $gitlabUrl = config('services.gitlab.instance_uri', 'https://gitlab.com');
        
        $response = $this->client->get("{$gitlabUrl}/api/v4/projects/{$projectId}/repository/branches", [
            'headers' => [
                'Authorization' => "Bearer {$account->token}",
            ],
        ]);

        $branches = json_decode($response->getBody()->getContents(), true);
        
        return array_map(fn($branch) => $branch['name'], $branches);
    }

    /**
     * Setup deploy key using OAuth token
     */
    public function setupOAuthDeployKey(GitDeployment $deployment): ?string
    {
        if (!$deployment->usesOAuth()) {
            return null;
        }

        $account = $deployment->connectedAccount;
        
        try {
            switch ($account->provider) {
                case 'github':
                    return $this->setupGitHubDeployKey($account, $deployment);
                case 'gitlab':
                    return $this->setupGitLabDeployKey($account, $deployment);
                default:
                    Log::warning("Unsupported OAuth provider for deploy key: {$account->provider}");
                    return null;
            }
        } catch (Exception $e) {
            Log::error("Failed to setup OAuth deploy key: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Setup GitHub deploy key via OAuth
     */
    protected function setupGitHubDeployKey(ConnectedAccount $account, GitDeployment $deployment): ?string
    {
        // For GitHub, we can use the OAuth token directly with HTTPS clone
        // This is more secure than deploy keys and easier to manage
        return $this->getGitHubOAuthCloneUrl($account, $deployment->repository_url);
    }

    /**
     * Setup GitLab deploy key via OAuth
     */
    protected function setupGitLabDeployKey(ConnectedAccount $account, GitDeployment $deployment): ?string
    {
        // For GitLab, we can use the OAuth token directly with HTTPS clone
        return $this->getGitLabOAuthCloneUrl($account, $deployment->repository_url);
    }

    /**
     * Get GitHub OAuth authenticated clone URL
     */
    protected function getGitHubOAuthCloneUrl(ConnectedAccount $account, string $repoUrl): string
    {
        // Convert SSH URL to HTTPS if needed
        if (str_starts_with($repoUrl, 'git@github.com:')) {
            $repoUrl = str_replace('git@github.com:', 'https://github.com/', $repoUrl);
        }
        
        // Ensure URL ends with .git
        if (!str_ends_with($repoUrl, '.git')) {
            $repoUrl .= '.git';
        }

        // Add OAuth token to URL
        return str_replace('https://', "https://{$account->token}@", $repoUrl);
    }

    /**
     * Get GitLab OAuth authenticated clone URL
     */
    protected function getGitLabOAuthCloneUrl(ConnectedAccount $account, string $repoUrl): string
    {
        $gitlabDomain = parse_url(config('services.gitlab.instance_uri', 'https://gitlab.com'), PHP_URL_HOST);
        
        // Convert SSH URL to HTTPS if needed
        if (str_starts_with($repoUrl, "git@{$gitlabDomain}:")) {
            $repoUrl = str_replace("git@{$gitlabDomain}:", "https://{$gitlabDomain}/", $repoUrl);
        }
        
        // Ensure URL ends with .git
        if (!str_ends_with($repoUrl, '.git')) {
            $repoUrl .= '.git';
        }

        // Add OAuth token to URL (GitLab uses oauth2 as username)
        return str_replace('https://', "https://oauth2:{$account->token}@", $repoUrl);
    }

    /**
     * Refresh OAuth token if expired
     */
    public function refreshTokenIfNeeded(ConnectedAccount $account): bool
    {
        // Check if token is expired or about to expire (within 5 minutes)
        if ($account->expires_at && $account->expires_at->lessThan(now()->addMinutes(5))) {
            return $this->refreshOAuthToken($account);
        }

        return true;
    }

    /**
     * Refresh OAuth token
     */
    protected function refreshOAuthToken(ConnectedAccount $account): bool
    {
        if (!$account->refresh_token) {
            Log::warning("No refresh token available for account {$account->id}");
            return false;
        }

        try {
            switch ($account->provider) {
                case 'github':
                    return $this->refreshGitHubToken($account);
                case 'gitlab':
                    return $this->refreshGitLabToken($account);
                default:
                    Log::warning("Unsupported OAuth provider for token refresh: {$account->provider}");
                    return false;
            }
        } catch (Exception $e) {
            Log::error("Failed to refresh OAuth token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh GitHub OAuth token
     */
    protected function refreshGitHubToken(ConnectedAccount $account): bool
    {
        $response = $this->client->post('https://github.com/login/oauth/access_token', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (isset($data['access_token'])) {
            $account->update([
                'token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                'expires_at' => isset($data['expires_in']) 
                    ? now()->addSeconds($data['expires_in']) 
                    : null,
            ]);
            
            return true;
        }

        return false;
    }

    /**
     * Refresh GitLab OAuth token
     */
    protected function refreshGitLabToken(ConnectedAccount $account): bool
    {
        $gitlabUrl = config('services.gitlab.instance_uri', 'https://gitlab.com');
        
        $response = $this->client->post("{$gitlabUrl}/oauth/token", [
            'json' => [
                'client_id' => config('services.gitlab.client_id'),
                'client_secret' => config('services.gitlab.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (isset($data['access_token'])) {
            $account->update([
                'token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                'expires_at' => isset($data['expires_in']) 
                    ? now()->addSeconds($data['expires_in']) 
                    : null,
            ]);
            
            return true;
        }

        return false;
    }
}
