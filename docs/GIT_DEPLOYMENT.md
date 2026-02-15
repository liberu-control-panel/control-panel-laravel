# Git Repository Auto-Deployment

This guide explains how to automatically deploy applications from Git repositories (GitHub, GitLab, Bitbucket, or any Git repository) using the Liberu Control Panel.

## Features

- **Multi-Platform Support**: Works with GitHub, GitLab, Bitbucket, and custom Git servers
- **Private Repositories**: Deploy from private repositories using SSH deploy keys
- **Auto-Deployment**: Automatically deploy on push via webhooks
- **Build Commands**: Run build scripts after deployment (npm, composer, etc.)
- **Branch Selection**: Deploy specific branches
- **Deployment History**: Track commits and deployment logs
- **Webhook Security**: Validate webhooks using signatures or tokens

## Prerequisites

Before setting up Git deployment:

1. A domain configured in the control panel
2. A Git repository (public or private)
3. A server with SSH and Git installed
4. SSH access to the server

## Setting Up Git Deployment

### Via Web Interface

1. Navigate to **Applications** → **Git Deployments**
2. Click **Create** to add a new deployment
3. Fill in the configuration form:

   **Domain Configuration:**
   - **Domain**: Select target domain
   - **Deploy Path**: Path where code will be deployed (default: `/public_html`)

   **Repository Configuration:**
   - **Repository URL**: Full Git repository URL
     - HTTPS: `https://github.com/username/repo.git`
     - SSH: `git@github.com:username/repo.git`
   - **Repository Type**: Auto-detected (GitHub, GitLab, Bitbucket, Other)
   - **Branch**: Branch to deploy (e.g., `main`, `master`, `develop`)
   - **Deploy Key**: SSH private key for private repositories (optional)

   **Build & Deploy Commands:**
   - **Build Command**: Commands to run after pulling code
     ```bash
     npm install && npm run build
     ```
   - **Deploy Command**: Additional deployment commands
     ```bash
     composer install --no-dev --optimize-autoloader
     ```

   **Automation:**
   - **Enable Auto-Deploy**: Automatically deploy on webhook triggers
   - **Webhook Secret**: Generate or enter a secret for webhook validation

4. Click **Create** to save the configuration

### Deploying the Repository

#### Manual Deployment

1. Go to **Applications** → **Git Deployments**
2. Find your deployment
3. Click the **Deploy** button
4. Confirm the deployment
5. Monitor progress via **Status** column
6. View logs with **View Logs**

#### Automatic Deployment (Webhooks)

Set up webhooks to automatically deploy when you push code:

**For GitHub:**

1. Go to your repository on GitHub
2. Navigate to **Settings** → **Webhooks** → **Add webhook**
3. Configure:
   - **Payload URL**: `https://your-control-panel.com/api/webhooks/github/{deployment_id}`
   - **Content type**: `application/json`
   - **Secret**: Copy from control panel
   - **Events**: Select "Just the push event"
4. Click **Add webhook**

**For GitLab:**

1. Go to your repository on GitLab
2. Navigate to **Settings** → **Webhooks**
3. Configure:
   - **URL**: `https://your-control-panel.com/api/webhooks/gitlab/{deployment_id}`
   - **Secret token**: Copy from control panel
   - **Trigger**: Check "Push events"
4. Click **Add webhook**

**For Bitbucket or Others:**

1. Use the generic webhook endpoint
2. URL: `https://your-control-panel.com/api/webhooks/generic/{deployment_id}?secret=YOUR_SECRET`
3. Configure push events in your repository settings

## Private Repositories

To deploy from private repositories, you need to set up SSH deploy keys:

### Generate Deploy Key

On your local machine or server:

```bash
ssh-keygen -t ed25519 -C "deploy-key-for-mysite" -f ~/.ssh/deploy_key_mysite
```

This creates two files:
- `deploy_key_mysite` (private key)
- `deploy_key_mysite.pub` (public key)

### Add Public Key to Repository

**GitHub:**
1. Go to repository **Settings** → **Deploy keys**
2. Click **Add deploy key**
3. Paste contents of `deploy_key_mysite.pub`
4. Name it (e.g., "Production Deploy Key")
5. Leave "Allow write access" unchecked
6. Click **Add key**

**GitLab:**
1. Go to repository **Settings** → **Repository** → **Deploy Keys**
2. Add the public key

**Bitbucket:**
1. Go to repository **Settings** → **Access keys**
2. Add the public key

### Add Private Key to Control Panel

1. Copy the entire contents of `deploy_key_mysite` (including headers)
2. In the control panel, edit your Git deployment
3. Paste the private key in the **Deploy Key** field
4. Save the configuration

## Deployment Process

When you trigger a deployment:

1. **Connect to Server**: SSH connection established
2. **Clone/Pull Repository**:
   - First deployment: Clone the repository
   - Subsequent deployments: Pull latest changes
3. **Record Commit**: Store current commit hash
4. **Run Build Command**: Execute build steps if configured
5. **Run Deploy Command**: Execute deployment steps if configured
6. **Set Permissions**: Apply proper file permissions (755)
7. **Log Results**: Record deployment log

## Deployment Status

Deployments can have the following statuses:

- **Pending**: Configuration saved, awaiting first deployment
- **Cloning**: Initial repository clone in progress
- **Deployed**: Successfully deployed
- **Updating**: Pulling latest changes
- **Failed**: Deployment failed (check logs)

## Managing Deployments

### Viewing Repository Information

To see current repository state:

1. Find your deployment in the list
2. Click **Repository Info**
3. View:
   - Current branch
   - Latest commit hash
   - Commit author and date
   - Commit message

### Viewing Deployment Logs

To troubleshoot deployment issues:

1. Click **View Logs** on your deployment
2. Review the deployment log
3. Check for errors or warnings

### Re-deploying

To deploy the latest code:

1. Click **Deploy** button
2. Confirm the action
3. The system will pull latest changes and redeploy

## Build & Deploy Commands

### Common Build Commands

**Node.js/React/Vue:**
```bash
npm install && npm run build
```

**Laravel:**
```bash
composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache
```

**Python:**
```bash
pip install -r requirements.txt
```

**Ruby:**
```bash
bundle install --deployment
```

### Common Deploy Commands

**Clear caches:**
```bash
php artisan cache:clear && php artisan view:clear
```

**Database migrations:**
```bash
php artisan migrate --force
```

**Restart services:**
```bash
sudo systemctl restart php8.2-fpm
```

**Set permissions:**
```bash
chmod -R 755 storage bootstrap/cache
```

## Repository URL Formats

The system supports various Git URL formats:

### HTTPS Format
```
https://github.com/username/repository.git
https://gitlab.com/username/repository.git
https://bitbucket.org/username/repository.git
```

### SSH Format
```
git@github.com:username/repository.git
git@gitlab.com:username/repository.git
git@bitbucket.org:username/repository.git
```

### Custom Git Servers
```
https://git.example.com/username/repository.git
git@git.example.com:username/repository.git
```

## Webhook Validation

Webhooks are validated to prevent unauthorized deployments:

### GitHub
- Uses HMAC SHA256 signature
- Header: `X-Hub-Signature-256`
- Validates against webhook secret

### GitLab
- Uses simple token validation
- Header: `X-Gitlab-Token`
- Must match webhook secret

### Generic
- Query parameter or header: `X-Webhook-Secret`
- Must match configured secret

## Troubleshooting

### Clone/Pull Fails

**Permission Denied (Public Key)**
- Verify deploy key is added to repository
- Check private key in control panel is correct
- Ensure deploy key has read access

**Repository Not Found**
- Verify repository URL is correct
- Check repository visibility (public/private)
- Ensure deploy key is configured for private repos

### Build Command Fails

- Check build command syntax
- Verify required tools are installed (npm, composer, etc.)
- Review deployment logs for specific errors
- Ensure proper file permissions

### Webhook Not Triggering

- Verify webhook URL is correct
- Check webhook secret matches
- Review webhook delivery logs in GitHub/GitLab
- Ensure auto-deploy is enabled

### File Permission Issues

```bash
# SSH into server and fix permissions
cd /var/www/yourdomain.com/public_html
chmod -R 755 .
chown -R www-data:www-data .
```

## Security Best Practices

1. **Use Deploy Keys**: Don't use personal SSH keys for deployments
2. **Read-Only Keys**: Deploy keys should only have read access
3. **Webhook Secrets**: Always use strong webhook secrets
4. **HTTPS Webhooks**: Use HTTPS URLs for webhook endpoints
5. **Limit Auto-Deploy**: Only enable for trusted branches
6. **Review Commands**: Audit build and deploy commands
7. **Monitor Logs**: Regularly check deployment logs

## API Integration

For programmatic deployments:

```bash
# Create Git deployment
curl -X POST https://your-control-panel.com/api/git-deployments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "domain_id": 1,
    "repository_url": "https://github.com/user/repo.git",
    "branch": "main",
    "auto_deploy": true,
    "build_command": "npm install && npm run build"
  }'

# Trigger deployment
curl -X POST https://your-control-panel.com/api/git-deployments/{id}/deploy \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Example Workflows

### Laravel Application

```
Repository URL: git@github.com:yourcompany/laravel-app.git
Branch: production
Build Command:
  composer install --no-dev --optimize-autoloader
Deploy Command:
  php artisan migrate --force
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
```

### React Application

```
Repository URL: https://github.com/yourcompany/react-app.git
Branch: main
Build Command:
  npm install
  npm run build
Deploy Command:
  cp -r build/* /var/www/yourdomain.com/public_html/
```

### Static Site

```
Repository URL: https://github.com/yourcompany/static-site.git
Branch: main
Build Command: (none)
Deploy Command: (none)
```

## Support

For issues or questions:
- Review deployment logs for specific errors
- Check this documentation
- Visit GitHub issues
- Contact support at https://liberu.co.uk
