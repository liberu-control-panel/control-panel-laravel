<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\GitDeployment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class GitDeploymentService
{
    protected SshConnectionService $sshService;
    protected OAuthRepositoryService $oauthService;

    public function __construct(SshConnectionService $sshService, OAuthRepositoryService $oauthService)
    {
        $this->sshService = $sshService;
        $this->oauthService = $oauthService;
    }

    /**
     * Deploy a repository
     */
    public function deploy(GitDeployment $deployment): bool
    {
        try {
            $domain = $deployment->domain;
            $server = $domain->server;

            if (!$server) {
                throw new Exception("No server assigned to domain {$domain->domain_name}");
            }

            // Validate repository URL
            if (!$this->isValidRepositoryUrl($deployment->repository_url)) {
                throw new Exception("Invalid repository URL");
            }

            // Update status
            $deployment->update([
                'status' => $deployment->last_deployed_at ? 'updating' : 'cloning',
                'deployment_log' => 'Starting deployment...'
            ]);

            // Execute deployment
            $log = $this->executeDeployment($server, $domain, $deployment);

            // Update status
            $deployment->update([
                'status' => 'deployed',
                'deployment_log' => $log,
                'last_deployed_at' => now(),
            ]);

            Log::info("Git deployment successful for domain {$domain->domain_name}");
            return true;

        } catch (Exception $e) {
            Log::error("Git deployment failed: " . $e->getMessage());
            
            $deployment->update([
                'status' => 'failed',
                'deployment_log' => $deployment->deployment_log . "\n\nError: " . $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Execute the git deployment
     */
    protected function executeDeployment($server, Domain $domain, GitDeployment $deployment): string
    {
        $log = [];
        
        $connection = $this->sshService->connect($server);
        
        if (!$connection) {
            throw new Exception("Failed to connect to server");
        }

        try {
            $deployPath = $this->getDeploymentPath($domain, $deployment);
            $repoPath = "{$deployPath}/.git";

            // Check if repository already exists
            $exists = $this->sshService->executeCommand($connection, "test -d {$repoPath} && echo 'exists' || echo 'new'");
            $isNew = trim($exists) === 'new';

            if ($isNew) {
                // Clone repository
                $log[] = "Cloning repository...";
                $this->cloneRepository($connection, $deployment, $deployPath);
            } else {
                // Pull latest changes
                $log[] = "Pulling latest changes...";
                $this->pullRepository($connection, $deployment, $deployPath);
            }

            // Get current commit hash
            $commitHash = trim($this->sshService->executeCommand(
                $connection,
                "cd {$deployPath} && git rev-parse HEAD"
            ));
            
            $deployment->update(['last_commit_hash' => $commitHash]);
            $log[] = "Current commit: {$commitHash}";

            // Execute build command if provided
            if ($deployment->build_command) {
                $log[] = "Running build command...";
                $buildOutput = $this->sshService->executeCommand(
                    $connection,
                    "cd {$deployPath} && {$deployment->build_command}"
                );
                $log[] = "Build output: " . $buildOutput;
            }

            // Execute deploy command if provided
            if ($deployment->deploy_command) {
                $log[] = "Running deploy command...";
                $deployOutput = $this->sshService->executeCommand(
                    $connection,
                    "cd {$deployPath} && {$deployment->deploy_command}"
                );
                $log[] = "Deploy output: " . $deployOutput;
            }

            // Set proper permissions
            $log[] = "Setting permissions...";
            $this->sshService->executeCommand($connection, "chmod -R 755 {$deployPath}");

            return implode("\n", $log);

        } finally {
            $this->sshService->disconnect($connection);
        }
    }

    /**
     * Clone a repository
     */
    protected function cloneRepository($connection, GitDeployment $deployment, string $deployPath): void
    {
        // Create deployment directory
        $this->sshService->executeCommand($connection, "mkdir -p " . dirname($deployPath));

        // Determine clone URL and authentication method
        $cloneUrl = $deployment->repository_url;
        $gitSshCommand = '';

        if ($deployment->usesOAuth()) {
            // Use OAuth token for authentication
            $account = $deployment->connectedAccount;
            $this->oauthService->refreshTokenIfNeeded($account);
            $cloneUrl = $this->oauthService->setupOAuthDeployKey($deployment);
        } elseif ($deployment->isPrivate()) {
            // Setup SSH key if private repository
            $this->setupDeployKey($connection, $deployment);
            $keyPath = $this->getDeployKeyPath($deployment);
            $gitSshCommand = "GIT_SSH_COMMAND='ssh -i {$keyPath} -o StrictHostKeyChecking=no' ";
        }

        // Clone command
        $cloneCommand = sprintf(
            "%sgit clone --branch %s %s %s",
            $gitSshCommand,
            escapeshellarg($deployment->branch),
            escapeshellarg($cloneUrl),
            escapeshellarg($deployPath)
        );

        $this->sshService->executeCommand($connection, $cloneCommand);
    }

    /**
     * Pull repository changes
     */
    protected function pullRepository($connection, GitDeployment $deployment, string $deployPath): void
    {
        // Determine authentication method
        $gitSshCommand = '';

        if ($deployment->usesOAuth()) {
            // Refresh OAuth token if needed
            $account = $deployment->connectedAccount;
            $this->oauthService->refreshTokenIfNeeded($account);
            
            // Update remote URL to use OAuth token
            $oauthUrl = $this->oauthService->setupOAuthDeployKey($deployment);
            $this->sshService->executeCommand(
                $connection,
                sprintf("cd %s && git remote set-url origin %s", 
                    escapeshellarg($deployPath),
                    escapeshellarg($oauthUrl)
                )
            );
        } elseif ($deployment->isPrivate()) {
            // Setup SSH key if private repository
            $this->setupDeployKey($connection, $deployment);
            $keyPath = $this->getDeployKeyPath($deployment);
            $gitSshCommand = "GIT_SSH_COMMAND='ssh -i {$keyPath} -o StrictHostKeyChecking=no' ";
        }

        // Pull command
        $pullCommand = sprintf(
            "cd %s && %sgit checkout %s && %sgit pull origin %s",
            escapeshellarg($deployPath),
            $gitSshCommand,
            escapeshellarg($deployment->branch),
            $gitSshCommand,
            escapeshellarg($deployment->branch)
        );

        $this->sshService->executeCommand($connection, $pullCommand);
    }

    /**
     * Setup deploy key for private repositories
     */
    protected function setupDeployKey($connection, GitDeployment $deployment): void
    {
        if (!$deployment->deploy_key) {
            return;
        }

        $keyPath = $this->getDeployKeyPath($deployment);
        $keyDir = dirname($keyPath);

        // Create SSH directory
        $this->sshService->executeCommand($connection, "mkdir -p {$keyDir}");
        $this->sshService->executeCommand($connection, "chmod 700 {$keyDir}");

        // Write deploy key
        $escapedKey = str_replace("'", "'\\''", $deployment->deploy_key);
        $this->sshService->executeCommand(
            $connection,
            "echo '{$escapedKey}' > {$keyPath}"
        );
        $this->sshService->executeCommand($connection, "chmod 600 {$keyPath}");
    }

    /**
     * Get deploy key path
     */
    protected function getDeployKeyPath(GitDeployment $deployment): string
    {
        return "/tmp/deploy_keys/deploy_key_{$deployment->id}";
    }

    /**
     * Get the deployment path
     */
    protected function getDeploymentPath(Domain $domain, GitDeployment $deployment): string
    {
        // If running in container, use container path
        $container = $domain->getWebContainer();
        
        if ($container) {
            return "/var/www/{$domain->domain_name}" . $deployment->deploy_path;
        }

        // Otherwise use direct server path
        return "/var/www/{$domain->domain_name}" . $deployment->deploy_path;
    }

    /**
     * Validate repository URL
     */
    public function isValidRepositoryUrl(string $url): bool
    {
        // Check for common git URL patterns
        $patterns = [
            '/^https?:\/\/.+\.git$/',                          // HTTPS with .git
            '/^https?:\/\/(github|gitlab|bitbucket)\.com\//', // Common hosts
            '/^git@.+:.+\.git$/',                              // SSH format
            '/^ssh:\/\/.+\.git$/',                             // SSH protocol
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        // Also allow HTTPS URLs without .git suffix
        if (preg_match('/^https?:\/\/.+\/.+/', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Generate webhook secret
     */
    public function generateWebhookSecret(): string
    {
        return Str::random(40);
    }

    /**
     * Validate webhook signature (GitHub)
     */
    public function validateGitHubWebhook(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate webhook signature (GitLab)
     */
    public function validateGitLabWebhook(string $token, string $secret): bool
    {
        return hash_equals($secret, $token);
    }

    /**
     * Handle webhook deployment
     */
    public function handleWebhook(GitDeployment $deployment, array $payload): bool
    {
        if (!$deployment->auto_deploy) {
            Log::info("Auto-deploy disabled for deployment {$deployment->id}");
            return false;
        }

        // Extract branch from payload (GitHub/GitLab format)
        $ref = $payload['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);

        // Only deploy if branch matches
        if ($branch !== $deployment->branch) {
            Log::info("Branch mismatch: {$branch} vs {$deployment->branch}");
            return false;
        }

        // Trigger deployment
        return $this->deploy($deployment);
    }

    /**
     * Get repository information
     */
    public function getRepositoryInfo(GitDeployment $deployment): ?array
    {
        try {
            $domain = $deployment->domain;
            $server = $domain->server;

            if (!$server || !$deployment->isDeployed()) {
                return null;
            }

            $connection = $this->sshService->connect($server);
            
            if (!$connection) {
                return null;
            }

            try {
                $deployPath = $this->getDeploymentPath($domain, $deployment);

                // Get current branch
                $branch = trim($this->sshService->executeCommand(
                    $connection,
                    "cd {$deployPath} && git rev-parse --abbrev-ref HEAD"
                ));

                // Get latest commit
                $commit = trim($this->sshService->executeCommand(
                    $connection,
                    "cd {$deployPath} && git log -1 --format='%H|%an|%ae|%ar|%s'"
                ));

                if ($commit) {
                    [$hash, $author, $email, $date, $message] = explode('|', $commit, 5);
                    
                    return [
                        'branch' => $branch,
                        'commit_hash' => $hash,
                        'commit_author' => $author,
                        'commit_email' => $email,
                        'commit_date' => $date,
                        'commit_message' => $message,
                    ];
                }

                return null;

            } finally {
                $this->sshService->disconnect($connection);
            }

        } catch (Exception $e) {
            Log::error("Failed to get repository info: " . $e->getMessage());
            return null;
        }
    }
}
