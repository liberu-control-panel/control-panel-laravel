# Unified Installation Script

This directory contains the unified installation script for Liberu Control Panel that simplifies deployment across different environments.

## Overview

The `install.sh` script provides an interactive installation wizard that supports:

- **Kubernetes** - Production-ready container orchestration with auto-scaling
- **Docker Compose** - Development and small-scale deployments
- **Standalone** - Traditional server installation without containers

## Supported Operating Systems

✅ **Ubuntu LTS**
- 20.04 (Focal Fossa)
- 22.04 (Jammy Jellyfish)  
- 24.04 (Noble Numbat)

✅ **Debian**
- 11 (Bullseye)
- 12 (Bookworm)

✅ **Red Hat Enterprise Linux Family**
- AlmaLinux 8, 9
- RHEL (Red Hat Enterprise Linux) 8, 9
- Rocky Linux 8, 9

## Quick Start

### Basic Installation

```bash
# Clone the repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Run the installer (requires root/sudo)
sudo ./install.sh
```

### Automated Installation (Non-Interactive)

For automated/scripted installations, you can set environment variables:

```bash
# Kubernetes installation
export INSTALLATION_METHOD=kubernetes
sudo -E ./install.sh

# Docker Compose installation  
export INSTALLATION_METHOD=docker
export DOMAIN=control.example.com
export EMAIL=admin@example.com
sudo -E ./install.sh

# Standalone installation
export INSTALLATION_METHOD=standalone
export DOMAIN=control.example.com
export EMAIL=admin@example.com
sudo -E ./install.sh
```

## Installation Methods

### 1. Kubernetes

**Best for:** Production environments requiring high availability and auto-scaling

**What's installed:**
- Kubernetes cluster (or connects to existing)
- NGINX Ingress Controller
- cert-manager for SSL automation
- Control Panel with all services
- MariaDB cluster
- Redis cache
- Mail services (Postfix/Dovecot)
- DNS cluster (PowerDNS)
- PHP multi-version support

**Resource requirements:**
- Minimum: 4GB RAM, 2 CPU cores, 40GB disk
- Recommended: 8GB RAM, 4 CPU cores, 100GB disk

**Post-installation access:**
```bash
kubectl get svc -n ingress-nginx
# Point your domain to the ingress IP
# Access: https://your-domain.com
```

### 2. Docker Compose

**Best for:** Development, testing, and single-server production

**What's installed:**
- Docker Engine
- Docker Compose
- NGINX reverse proxy
- Control Panel application
- MariaDB/PostgreSQL database
- Redis cache
- Mail services
- DNS server (BIND9)
- Let's Encrypt SSL

**Resource requirements:**
- Minimum: 2GB RAM, 2 CPU cores, 20GB disk
- Recommended: 4GB RAM, 2 CPU cores, 40GB disk

**Post-installation access:**
```bash
docker-compose ps
# Access: http://your-domain.com
```

### 3. Standalone

**Best for:** Traditional server setups, legacy environments

**What's installed:**
- NGINX web server
- PHP 8.3 with FPM
- MariaDB database
- Redis cache
- Composer
- Node.js/npm
- Certbot for SSL
- Mail services (Postfix/Dovecot)
- DNS server (BIND9)
- Control Panel application

**Resource requirements:**
- Minimum: 2GB RAM, 1 CPU core, 20GB disk
- Recommended: 4GB RAM, 2 CPU cores, 40GB disk

**Post-installation access:**
```bash
systemctl status nginx
cd /var/www/control-panel
# Access: http://your-domain.com
```

## Script Structure

```
install.sh
├── Banner & UI Functions
├── OS Detection (Ubuntu/RHEL/AlmaLinux/Rocky)
├── Common Prerequisites Installation
├── Installation Methods
│   ├── Kubernetes
│   │   ├── Cluster Setup (via install-k8s.sh)
│   │   └── Control Panel Deployment (via install-control-panel.sh)
│   ├── Docker Compose
│   │   ├── Docker Engine Installation
│   │   ├── Secrets Generation
│   │   ├── Environment Setup
│   │   └── Container Orchestration
│   └── Standalone
│       ├── Service Installation (NGINX, PHP, MariaDB, Redis)
│       ├── Application Setup
│       ├── Database Configuration
│       └── NGINX Virtual Host Configuration
└── Post-Installation Instructions
```

## Features

### Interactive Installation
- Color-coded output for better readability
- Step-by-step guidance
- OS detection and validation
- Installation method selection menu
- Configuration prompts

### Security
- Automatic password generation for databases
- SSL certificate setup (Let's Encrypt)
- Secure file permissions
- SELinux configuration (RHEL/AlmaLinux)

### Error Handling
- Syntax validation (`bash -n install.sh`)
- OS compatibility checks
- Service verification
- Rollback on critical failures

## Advanced Usage

### Custom Configuration

You can modify the script variables at runtime:

```bash
# Kubernetes with custom version
export K8S_VERSION=1.30
sudo ./install.sh

# Docker with custom domain
export DOMAIN=panel.mycompany.com
export EMAIL=admin@mycompany.com
sudo ./install.sh
```

### Testing the Script

```bash
# Check syntax
bash -n install.sh

# View available functions
grep "^[a-z_]*() {" install.sh

# Test OS detection (no root required)
bash -c '. install.sh; detect_os'
```

## Troubleshooting

### Common Issues

**1. Permission Denied**
```bash
chmod +x install.sh
sudo ./install.sh
```

**2. OS Not Supported**
```bash
cat /etc/os-release
# Verify OS is in supported list
```

**3. Installation Fails**
```bash
# Check logs based on method:
# Kubernetes: kubectl logs -n control-panel <pod>
# Docker: docker-compose logs
# Standalone: tail -f /var/www/control-panel/storage/logs/laravel.log
```

**4. Docker Installation Issues**
```bash
# Verify Docker installation
docker --version
docker-compose --version
systemctl status docker
```

**5. Kubernetes Installation Issues**
```bash
# Check cluster status
kubectl cluster-info
kubectl get nodes
kubectl get pods -A
```

## Integration with Existing Scripts

The unified installer leverages existing installation scripts:

- `install-k8s.sh` - Kubernetes cluster setup
- `install-control-panel.sh` - Control panel deployment on K8s
- `setup.sh` - Docker Compose setup helper
- `docker-compose.yml` - Container definitions

## Environment Variables

| Variable | Description | Default | Example |
|----------|-------------|---------|---------|
| `INSTALLATION_METHOD` | Installation type | Interactive | kubernetes, docker, standalone |
| `DOMAIN` | Domain name | localhost | control.example.com |
| `EMAIL` | Email for SSL | - | admin@example.com |
| `K8S_VERSION` | Kubernetes version | 1.29 | 1.30 |
| `DB_PASSWORD` | Database password | Auto-generated | - |
| `APP_KEY` | Laravel app key | Auto-generated | - |

## Post-Installation

After installation completes:

1. **Create Admin User**
   ```bash
   # Kubernetes
   kubectl exec -it -n control-panel <pod> -- php artisan make:filament-user
   
   # Docker
   docker-compose exec control-panel php artisan make:filament-user
   
   # Standalone
   cd /var/www/control-panel && php artisan make:filament-user
   ```

2. **Configure DNS**
   - Point your domain A record to server IP
   - Wait for DNS propagation (5-60 minutes)

3. **Verify SSL**
   - Access https://your-domain.com
   - Check certificate is valid

4. **Setup Firewall**
   ```bash
   # Ubuntu
   ufw allow 80/tcp
   ufw allow 443/tcp
   
   # RHEL/AlmaLinux
   firewall-cmd --permanent --add-service=http
   firewall-cmd --permanent --add-service=https
   firewall-cmd --reload
   ```

## Documentation

- [Installation Guide](docs/INSTALLATION_GUIDE.md) - Comprehensive installation documentation
- [Kubernetes Guide](docs/KUBERNETES_INSTALLATION.md) - Kubernetes-specific setup
- [Multi-Deployment Guide](docs/MULTI_DEPLOYMENT_AUTOSCALING.md) - Deployment options comparison
- [Security Guide](docs/SECURITY.md) - Security best practices

## Support

- **GitHub Issues**: https://github.com/liberu-control-panel/control-panel-laravel/issues
- **Documentation**: https://liberu.co.uk
- **Community**: GitHub Discussions

## License

This script is part of the Liberu Control Panel project and is licensed under the MIT License.
