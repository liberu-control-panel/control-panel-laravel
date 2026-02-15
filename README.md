# Liberu — Dockerised Webhosting Control Panel

[![](https://avatars.githubusercontent.com/u/158830885?s=200&v=4)](https://www.liberu.co.uk)

![](https://img.shields.io/badge/PHP-8.5-informational?style=flat&logo=php&color=4f5b93)
![](https://img.shields.io/badge/Laravel-12-informational?style=flat&logo=laravel&color=ef3b2d)
![](https://img.shields.io/badge/Filament-5-informational?style=flat&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgeG1sbnM6dj0iaHR0cHM6Ly92ZWN0YS5pby9uYW5vIj48cGF0aCBkPSJNMCAwaDQ4djQ4SDBWMHoiIGZpbGw9IiNmNGIyNWUiLz48cGF0aCBkPSJNMjggN2wtMSA2LTMuNDM3LjgxM0wyMCAxNWwtMSAzaDZ2NWgtN2wtMyAxOEg4Yy41MTUtNS44NTMgMS40NTQtMTEuMzMgMy0xN0g4di01bDUtMSAuMjUtMy4yNUMxNCAxMSAxNCAxMSAxNS40MzggOC41NjMgMTkuNDI5IDYuMTI4IDIzLjQ0MiA2LjY4NyAyOCA3eiIgZmlsbD0iIzI4MjQxZSIvPjxwYXRoIGQ9Ik0zMCAxOGg0YzIuMjMzIDUuMzM0IDIuMjMzIDUuMzM0IDEuMTI1IDguNUwzNCAyOWMtLjE2OCAzLjIwOS0uMTY4IDMuMjA5IDAgNmwtMiAxIDEgM2gtNXYyaC0yYy44NzUtNy42MjUuODc1LTcuNjI1IDItMTFoMnYtMmgtMnYtMmwyLTF2LTQtM3oiIGZpbGw9IiMyYTIwMTIiLz48cGF0aCBkPSJNMzUuNTYzIDYuODEzQzM4IDcgMzggNyAzOSA4Yy4xODggMi40MzguMTg4IDIuNDM4IDAgNWwtMiAyYy0yLjYyNS0uMzc1LTIuNjI1LS4zNzUtNS0xLS42MjUtMi4zNzUtLjYyNS0yLjM3NS0xLTUgMi0yIDItMiA0LjU2My0yLjE4N3oiIGZpbGw9IiM0MDM5MzEiLz48cGF0aCBkPSJNMzAgMThoNGMyLjA1NSA1LjMxOSAyLjA1NSA1LjMxOSAxLjgxMyA4LjMxM0wzNSAyOGwtMyAxdi0ybC00IDF2LTJsMi0xdi00LTN6IiBmaWxsPSIjMzEyODFlIi8+PHBhdGggZD0iTTI5IDI3aDN2MmgydjJoLTJ2MmwtNC0xdi0yaDJsLTEtM3oiIGZpbGw9IiMxNTEzMTAiLz48cGF0aCBkPSJNMzAgMThoNHYzaC0ydjJsLTMgMSAxLTZ6IiBmaWxsPSIjNjA0YjMyIi8+PC9zdmc+&&color=fdae4b&link=https://filamentphp.com)
![](https://img.shields.io/badge/Livewire-4.1-informational?style=flat&logo=Livewire&color=fb70a9)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![Open Source Love](https://img.shields.io/badge/Open%20Source-%E2%9D%A4-red.svg)


## Welcome to Liberu, our visionary open-source initiative that marries the power of Laravel 12, PHP 8.5 and Filament 5.2 to redefine the landscape of web development.
[![Install](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/install.yml/badge.svg)](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/install.yml) [![Tests](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/tests.yml) [![Docker](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/main.yml/badge.svg)](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/main.yml) [![Codecov](https://codecov.io/gh/liberu-control-panel/control-panel-laravel/branch/main/graph/badge.svg)](https://codecov.io/gh/liberu-control-panel/control-panel-laravel)


A modular, Docker-first Laravel control panel for managing web hosting: virtual hosts (NGINX), BIND DNS zones, Postfix/Dovecot mail, MySQL databases, and Docker Compose service orchestration. Designed for sysadmins and self-hosting teams who want a single web interface to manage hosting infrastructure.

Key features

- User and team management with Jetstream and role-based policies
- Manage NGINX virtual hosts with automated Let's Encrypt support
- BIND DNS zone and record management (A, AAAA, CNAME, MX, TXT, ...)
- Mail domain and mailbox management (Postfix + Dovecot)
- MySQL database + user lifecycle and backup/restore helpers
- Kubernetes and Docker orchestration: deploy, monitor, and manage services
- Secure SSH-based remote server management
- **S3-compatible storage support** for persistent volumes (AWS S3, MinIO, DigitalOcean Spaces, etc.)
- **WordPress auto-deployment** with one-click installation and automatic updates
- **Git repository deployment** from GitHub, GitLab, Bitbucket with webhook support

## Quick Start

### Complete Kubernetes Installation (Recommended)

For a **complete, production-ready installation** including Kubernetes cluster setup, control panel, and all services (mail, DNS, PHP multi-version, etc.), see the [Complete Kubernetes Installation Guide](docs/KUBERNETES_INSTALLATION.md).

**What's Included:**
- Automated Kubernetes cluster installation (Ubuntu LTS & AlmaLinux/RHEL)
- Control Panel with Laravel Octane support
- NGINX Ingress with Let's Encrypt SSL
- MariaDB cluster with replication
- Redis for caching
- Postfix + Dovecot mail services
- PowerDNS DNS cluster
- PHP multi-version support (8.1-8.5)
- Queue workers and scheduler
- **S3-compatible storage integration** for persistent volumes

**Quick Installation:**

```bash
# Step 1: Install Kubernetes cluster
sudo ./install-k8s.sh

# Step 2: Install control panel and all services
# The script will prompt for S3 storage configuration
./install-control-panel.sh
```

**Storage Options:**
- During installation, you'll be prompted to configure S3-compatible storage
- Supports AWS S3, MinIO, DigitalOcean Spaces, Backblaze B2, Cloudflare R2, and more
- **All services including MariaDB can use S3 storage** for persistent volumes
- See [S3 Storage Guide](docs/S3_STORAGE.md) for detailed configuration

### Kubernetes Deployment (Manual)

Deploy the control panel on an existing Kubernetes cluster. See the detailed [Kubernetes Setup Guide](docs/KUBERNETES_SETUP.md) for complete instructions.

**Prerequisites:**
- Kubernetes cluster (v1.20+)
- NGINX Ingress Controller
- cert-manager (for automatic SSL certificates)
- kubectl and Helm installed locally

#### Option 1: Deploy with Helm

```bash
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Generate app key
APP_KEY=$(php artisan key:generate --show)

# Install with Helm
helm install control-panel ./helm/control-panel \
  --set app.key="$APP_KEY" \
  --set app.url="https://control.yourdomain.com" \
  --set mysql.auth.password="secure-password" \
  --set mysql.auth.rootPassword="secure-root-password" \
  --set ingress.hosts[0].host="control.yourdomain.com" \
  --namespace control-panel \
  --create-namespace

# Optional: Enable S3 storage for persistent volumes
helm install control-panel ./helm/control-panel \
  --set app.key="$APP_KEY" \
  --set app.url="https://control.yourdomain.com" \
  --set s3.enabled=true \
  --set s3.endpoint="https://s3.amazonaws.com" \
  --set s3.accessKey="your-access-key" \
  --set s3.secretKey="your-secret-key" \
  --set s3.bucket="control-panel-storage" \
  --set s3.region="us-east-1" \
  --namespace control-panel \
  --create-namespace
```

#### Option 2: Deploy with Kustomize

```bash
export APP_KEY="base64:YOUR_KEY"
export DB_PASSWORD="secure-password"
export DB_ROOT_PASSWORD="secure-root-password"
export DOMAIN="control.yourdomain.com"

./k8s/deploy.sh
```

#### Option 3: Deploy with Makefile

```bash
make deploy-prod
make migrate
make status
```

### Managing Kubernetes Clusters

After deploying the control panel, you can use it to manage remote Kubernetes clusters:

1. Access the web interface at your configured domain
2. Navigate to **Servers** → **Create Server**
3. Add your Kubernetes cluster details with SSH credentials
4. Deploy customer applications to managed Kubernetes clusters

### Docker Deployment (Legacy)

For development or legacy deployments, use Docker Compose:

1. Clone the repository and switch to the project directory:

```
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel
```

2. Copy the example environment and adjust the values you need:

```
cp .env.example .env
# Edit .env: set CONTROL_PANEL_DOMAIN, LETSENCRYPT_EMAIL, DB credentials, etc.
```

3. Start the services (build on first run):

```
docker compose up -d --build
```

4. (Optional) Run database migrations and seeders inside the main app container:

```
docker compose exec control-panel php artisan migrate --force
docker compose exec control-panel php artisan db:seed --class=DatabaseSeeder
```

5. Open your browser at http://localhost (or the domain set in `CONTROL_PANEL_DOMAIN`).

Notes

- The `setup.sh` script in the repo automates build + migrations + seeding for supported environments.
- For development using Laravel Sail follow Sail's instructions (see repository docs).

## Documentation

### Infrastructure & Deployment
- **[Complete Kubernetes Installation](docs/KUBERNETES_INSTALLATION.md)** - Full installation guide for Kubernetes cluster and all services
- **[S3 Storage Guide](docs/S3_STORAGE.md)** - Configure S3-compatible storage for persistent volumes
- **[Helm GUI Installer](docs/HELM_GUI_INSTALLER.md)** - Graphical interface for installing services via Helm charts
- **[Kubernetes Setup Guide](docs/KUBERNETES_SETUP.md)** - Deploy control panel on existing Kubernetes cluster
- **[Kubernetes Manifests](k8s/README.md)** - Raw Kubernetes manifests and Kustomize overlays
- **[Helm Chart](helm/control-panel/README.md)** - Helm chart for easy deployment
- **[Mail Services](helm/mail-services/README.md)** - Postfix and Dovecot mail services
- **[DNS Cluster](helm/dns-cluster/README.md)** - PowerDNS DNS cluster setup
- **[PHP Multi-Version](helm/php-versions/README.md)** - PHP 8.1-8.5 multi-version support

### Application Deployment
- **[WordPress Auto-Deployment](docs/WORDPRESS_DEPLOYMENT.md)** - Install and manage WordPress sites with one-click deployment
- **[Git Repository Deployment](docs/GIT_DEPLOYMENT.md)** - Deploy applications from GitHub, GitLab, Bitbucket, or any Git repository

### Security & Configuration
- **[SSH Configuration Guide](docs/SSH_CONFIGURATION.md)** - Configure secure SSH connections
- **[Security Best Practices](docs/SECURITY.md)** - Essential security guidelines
- **[Makefile Reference](#makefile-commands)** - Common deployment commands

## Deployment Architecture

### Control Panel on Kubernetes

When deployed on Kubernetes, the control panel uses this architecture:

```
┌─────────────────────────────────────────┐
│           Ingress (TLS)                 │
│  control-panel.yourdomain.com           │
└────────────┬────────────────────────────┘
             │
┌────────────▼────────────────────────────┐
│        Control Panel Service            │
└────────────┬────────────────────────────┘
             │
┌────────────▼────────────────────────────┐
│   Control Panel Deployment (2-10 pods)  │
│  ┌──────────────┐  ┌─────────────────┐ │
│  │   NGINX      │  │   PHP-FPM       │ │
│  │   (Alpine)   │  │  (Laravel App)  │ │
│  └──────────────┘  └─────────────────┘ │
└─────────┬───────────────────┬───────────┘
          │                   │
    ┌─────▼──────┐     ┌─────▼──────┐
    │   Redis    │     │   MySQL    │
    │  (Cache)   │     │ StatefulSet│
    └────────────┘     └────────────┘
```

### Managing Remote Kubernetes Clusters

```
Control Panel (Laravel Application)
    │ SSH Connection (Encrypted)
    ▼
Remote Kubernetes Server
├── Namespace: hosting-example-com
│   ├── Deployment: NGINX + PHP-FPM
│   ├── Service: Load balancer
│   ├── Ingress: TLS termination
│   ├── PVC: Persistent storage
│   ├── StatefulSet: Database (MySQL/PostgreSQL)
│   └── Security: RBAC + NetworkPolicy
```

### Security Layers

1. **SSH Layer**: Encrypted connections with key-based or password authentication
2. **Kubernetes RBAC**: Namespace isolation and limited privileges
3. **NetworkPolicies**: Traffic segmentation between services
4. **Resource Quotas**: Prevent resource exhaustion
5. **Pod Security**: Run as non-root, drop capabilities
6. **Secrets Management**: Encrypted credential storage

## Makefile Commands

The project includes a Makefile for common Kubernetes operations:

```bash
make help           # Show all available commands
make deploy-dev     # Deploy to development environment
make deploy-prod    # Deploy to production environment
make validate       # Validate Kubernetes manifests
make status         # Check deployment status
make logs           # View application logs
make migrate        # Run database migrations
make seed           # Seed database
make shell          # Open shell in application pod
make helm-install   # Install using Helm chart
make helm-upgrade   # Upgrade Helm release
make clean          # Remove deployment
```

Example workflow:

```bash
# Deploy to production
make deploy-prod

# Check status
make status

# Run migrations
make migrate

# View real-time logs
make logs
```

## Our projects

The Liberu ecosystem contains a number of companion repositories and packages that extend or demonstrate functionality used in this boilerplate. Below is a concise, professional list of those projects with quick descriptions — follow the links to learn more or to contribute.

| Project | Repository | Short description |
|---|---:|---|
| Accounting | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Accounting and invoicing features tailored for Laravel applications. |
| Automation | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Automation tooling and workflow integrations for Laravel projects. |
| Billing | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Subscription and billing management integrations (payments, invoices). |
| Boilerplate (core) | [liberusoftware/boilerplate](https://github.com/liberusoftware/boilerplate) | Core starter and shared utilities used across Liberu projects. |
| Browser Game | [liberu-browser-game/browser-game-laravel](https://github.com/liberu-browser-game/browser-game-laravel) | Example Laravel-based browser game platform and mechanics. |
| CMS | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Content management features and modular page administration. |
| Control Panel | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Administration/control-panel components for managing services. |
| CRM | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer relationship management features and integrations. |
| E‑commerce | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | E‑commerce storefront, product and order management. |
| Genealogy | [liberu-genealogy/genealogy-laravel](https://github.com/liberu-genealogy/genealogy-laravel) | Family tree and genealogy features built on Laravel. |
| Maintenance | [liberu-maintenance/maintenance-laravel](https://github.com/liberu-maintenance/maintenance-laravel) | Scheduling, tracking and reporting for maintenance tasks. |
| Real Estate | [liberu-real-estate/real-estate-laravel](https://github.com/liberu-real-estate/real-estate-laravel) | Property listings and real-estate management features. |
| Social Network | [liberu-social-network/social-network-laravel](https://github.com/liberu-social-network/social-network-laravel) | Social features, profiles, feeds and messaging for Laravel apps. |

If you maintain or use one of these projects and would like a more detailed description or a different categorisation, open an issue or submit a pull request and we'll update the list. Contributions and cross-repo collaboration are warmly encouraged.

Contributing

Contributions are welcome. Please open issues for bugs or feature requests, and submit pull requests from a feature branch. Ensure CI passes and include tests for new behavior where appropriate. For larger changes, open a short proposal issue first.

License

This project is licensed under the MIT License — see the LICENSE file for details.

Where to get help

- Use GitHub Issues for bugs and feature requests.
- For direct support or urgent questions, contact the maintainers via the project site: https://liberu.co.uk

Acknowledgements

Thanks to contributors and the open-source community. See the contributors graph below.

<a href="https://github.com/liberu-control-panel/control-panel-laravel/graphs/contributors"><img src="https://contrib.rocks/image?repo=liberu-control-panel/control-panel-laravel" alt="Contributors"/></a>
