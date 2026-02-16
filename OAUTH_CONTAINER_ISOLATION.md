# OAuth Integration & Container Isolation

This document describes the GitHub and GitLab OAuth integration for private repository provisioning, along with the container/pod isolation features.

## Table of Contents

- [OAuth for Private Repositories](#oauth-for-private-repositories)
- [Container Isolation](#container-isolation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Reference](#api-reference)

## OAuth for Private Repositories

The control panel now supports OAuth authentication with GitHub and GitLab, allowing users to deploy private repositories without manually managing SSH deploy keys.

### Features

- **GitHub OAuth Integration**: Authenticate with GitHub to access private repositories
- **GitLab OAuth Integration**: Authenticate with GitLab (including self-hosted instances)
- **Automatic Token Management**: Access tokens are automatically refreshed when needed
- **Repository Browser**: Browse and select repositories from connected OAuth accounts
- **Secure Deployment**: OAuth tokens are used securely for git operations without exposing credentials

### How It Works

1. User connects their GitHub or GitLab account via OAuth
2. OAuth credentials (access token, refresh token) are stored in the `connected_accounts` table
3. When creating a git deployment, user can choose to use their OAuth account
4. During deployment, the system uses the OAuth token to authenticate git operations
5. Tokens are automatically refreshed when they expire

## Container Isolation

Each website deployment can have its own isolated container or Kubernetes pod, ensuring resource isolation and security.

### Features

- **Docker Container Isolation**: Each website runs in its own Docker container
- **Kubernetes Pod Isolation**: Each website can run in its own Kubernetes pod with dedicated resources
- **Automatic Service Creation**: Kubernetes services and ingresses are automatically created
- **Resource Limits**: CPU and memory limits are enforced per container/pod
- **SSL/TLS Support**: Automatic certificate provisioning via cert-manager

### Deployment Methods

The system supports two deployment methods:

1. **Docker Containers**: Direct Docker container deployment on a single server
2. **Kubernetes Pods**: Full Kubernetes deployment with service mesh, ingress, and auto-scaling

## Configuration

### OAuth Configuration

#### 1. GitHub OAuth Setup

1. Go to GitHub Settings → Developer settings → OAuth Apps
2. Click "New OAuth App"
3. Fill in the details:
   - **Application name**: Your Control Panel Name
   - **Homepage URL**: Your control panel URL (e.g., `https://panel.example.com`)
   - **Authorization callback URL**: `https://panel.example.com/oauth/github/callback`
4. Click "Register application"
5. Copy the **Client ID** and **Client Secret**

#### 2. GitLab OAuth Setup

1. Go to GitLab User Settings → Applications
2. Fill in the details:
   - **Name**: Your Control Panel Name
   - **Redirect URI**: `https://panel.example.com/oauth/gitlab/callback`
   - **Scopes**: Select `api`, `read_user`, `read_repository`
3. Click "Save application"
4. Copy the **Application ID** and **Secret**

#### 3. Environment Variables

Add the following to your `.env` file:

```bash
# GitHub OAuth
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI="${APP_URL}/oauth/github/callback"

# GitLab OAuth (use default GitLab.com or self-hosted instance)
GITLAB_CLIENT_ID=your_gitlab_client_id
GITLAB_CLIENT_SECRET=your_gitlab_client_secret
GITLAB_REDIRECT_URI="${APP_URL}/oauth/gitlab/callback"
GITLAB_INSTANCE_URI=https://gitlab.com  # Or your GitLab instance URL
```

### Kubernetes Configuration

For Kubernetes pod isolation, configure the following environment variables:

```bash
# Kubernetes Configuration
KUBERNETES_ENABLED=true
KUBECTL_PATH=/usr/local/bin/kubectl
KUBERNETES_NAMESPACE_PREFIX=hosting-
KUBERNETES_INGRESS_CLASS=nginx
KUBERNETES_CERT_ISSUER=letsencrypt-prod
KUBERNETES_STORAGE_CLASS=standard
```

### Docker Configuration

For Docker container isolation:

```bash
# Docker Configuration
DOCKER_IMAGE=control-panel-laravel
DOCKER_WEB_IMAGE=nginx:alpine
```

## Usage

### Using OAuth for Git Deployments

#### 1. Connect OAuth Account

Users can connect their GitHub or GitLab account from their profile settings:

1. Navigate to Profile → Connected Accounts
2. Click "Connect GitHub" or "Connect GitLab"
3. Authorize the application
4. The account is now connected and tokens are stored securely

#### 2. Create Deployment with OAuth

When creating a new git deployment:

```php
use App\Models\GitDeployment;
use App\Models\ConnectedAccount;

// Get user's connected GitHub account
$connectedAccount = ConnectedAccount::where('user_id', $user->id)
    ->where('provider', 'github')
    ->first();

// Create deployment using OAuth
$deployment = GitDeployment::create([
    'domain_id' => $domain->id,
    'connected_account_id' => $connectedAccount->id,
    'use_oauth' => true,
    'repository_url' => 'https://github.com/user/private-repo.git',
    'repository_type' => 'github',
    'branch' => 'main',
    'deploy_path' => '/public_html',
    'auto_deploy' => true,
]);

// Deploy
app(GitDeploymentService::class)->deploy($deployment);
```

#### 3. Browse Repositories via OAuth

```php
use App\Services\OAuthRepositoryService;

$oauthService = app(OAuthRepositoryService::class);

// Get list of repositories
$repositories = $oauthService->getRepositories($connectedAccount, $page = 1, $perPage = 30);

// Get repository branches
$branches = $oauthService->getRepositoryBranches($connectedAccount, 'user/repo');
```

### Using Container Isolation

#### 1. Create Isolated Container

```php
use App\Services\ContainerIsolationService;

$isolationService = app(ContainerIsolationService::class);

// Create Docker container for deployment
$container = $isolationService->createIsolatedContainer($deployment);

// Or create Kubernetes pod
$isolationService->createKubernetesPod($deployment);
$isolationService->createKubernetesService($deployment);
$isolationService->createKubernetesIngress($deployment);

// Or setup complete isolation (tries Kubernetes first, falls back to Docker)
$isolationService->setupCompleteIsolation($deployment);
```

#### 2. Check Isolation Status

```php
// Check if deployment has container isolation
if ($deployment->hasContainerIsolation()) {
    echo "Deployment is isolated";
}

// Get container details
$container = $deployment->container;

// Get Kubernetes pod details
$podName = $deployment->kubernetes_pod_name;
$namespace = $deployment->kubernetes_namespace;
```

## API Reference

### OAuthRepositoryService

#### Methods

- `getRepositories(ConnectedAccount $account, int $page, int $perPage): array`
  - Fetch repositories from OAuth provider
  
- `getRepositoryBranches(ConnectedAccount $account, string $repoFullName): array`
  - Get branches for a specific repository
  
- `setupOAuthDeployKey(GitDeployment $deployment): ?string`
  - Generate OAuth-authenticated clone URL
  
- `refreshTokenIfNeeded(ConnectedAccount $account): bool`
  - Refresh OAuth token if expired

### ContainerIsolationService

#### Methods

- `createIsolatedContainer(GitDeployment $deployment): ?Container`
  - Create Docker container for deployment
  
- `createKubernetesPod(GitDeployment $deployment): bool`
  - Create Kubernetes pod for deployment
  
- `createKubernetesService(GitDeployment $deployment): bool`
  - Create Kubernetes service for pod
  
- `createKubernetesIngress(GitDeployment $deployment): bool`
  - Create Kubernetes ingress with SSL/TLS
  
- `setupCompleteIsolation(GitDeployment $deployment): bool`
  - Setup complete isolation (Kubernetes or Docker)

### GitDeployment Model

#### New Fields

- `connected_account_id`: Foreign key to connected OAuth account
- `use_oauth`: Boolean flag to use OAuth authentication
- `container_id`: Foreign key to isolated container
- `kubernetes_pod_name`: Name of Kubernetes pod
- `kubernetes_namespace`: Kubernetes namespace

#### New Methods

- `usesOAuth(): bool` - Check if deployment uses OAuth
- `hasContainerIsolation(): bool` - Check if deployment has container isolation
- `connectedAccount(): BelongsTo` - Get connected OAuth account relationship
- `container(): BelongsTo` - Get container relationship

## Security Considerations

1. **Token Storage**: OAuth tokens are stored encrypted in the database
2. **Token Refresh**: Tokens are automatically refreshed before expiration
3. **HTTPS Only**: OAuth callbacks must use HTTPS in production
4. **Container Isolation**: Each website runs in isolated environment with resource limits
5. **Non-root Containers**: All containers run as non-root user (UID 1000)
6. **Network Policies**: Kubernetes deployments can use network policies for additional isolation

## Troubleshooting

### OAuth Issues

**Problem**: "Failed to refresh OAuth token"
- **Solution**: User needs to reconnect their OAuth account

**Problem**: "Repository not found"
- **Solution**: Ensure the OAuth token has access to the repository

### Container Issues

**Problem**: "Kubernetes pod creation failed"
- **Solution**: Check that Kubernetes is enabled and properly configured

**Problem**: "Container name already exists"
- **Solution**: Check for naming conflicts, ensure unique container names

## Migration Guide

### Migrating Existing Deployments to OAuth

```php
use App\Models\GitDeployment;
use App\Models\ConnectedAccount;

// Find deployment with deploy key
$deployment = GitDeployment::find($id);

// Get user's connected account
$connectedAccount = ConnectedAccount::where('user_id', $deployment->domain->user_id)
    ->where('provider', $deployment->repository_type)
    ->first();

if ($connectedAccount) {
    // Migrate to OAuth
    $deployment->update([
        'connected_account_id' => $connectedAccount->id,
        'use_oauth' => true,
        'deploy_key' => null, // Remove old deploy key
    ]);
}
```

### Adding Container Isolation to Existing Deployments

```php
use App\Services\ContainerIsolationService;

$isolationService = app(ContainerIsolationService::class);

// Get all deployments without container isolation
$deployments = GitDeployment::whereNull('container_id')
    ->whereNull('kubernetes_pod_name')
    ->get();

foreach ($deployments as $deployment) {
    $isolationService->setupCompleteIsolation($deployment);
}
```

## License

This feature is part of the Liberu Control Panel and follows the same license.
