# Liberu Control Panel - Installation Guide

This guide covers the installation of Liberu Control Panel using the unified installation script that supports multiple deployment methods.

## Overview

The Liberu Control Panel provides a **unified installation script** (`install.sh`) that simplifies the deployment process by offering three installation methods:

1. **Kubernetes** - Recommended for production environments
2. **Docker Compose** - Ideal for development and small-scale deployments
3. **Standalone** - Traditional server installation without containers

## Supported Operating Systems

The installation script supports the following operating systems:

- **Ubuntu LTS**: 20.04, 22.04, 24.04
- **Debian**: 11 (Bullseye), 12 (Bookworm)
- **AlmaLinux**: 8, 9
- **RHEL (Red Hat Enterprise Linux)**: 8, 9
- **Rocky Linux**: 8, 9

## Quick Start

### Prerequisites

- Root or sudo access to your server
- Minimum 2GB RAM (4GB+ recommended for Kubernetes)
- 20GB+ available disk space
- Internet connection for downloading packages

### Installation Steps

1. **Clone the repository:**

```bash
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel
```

2. **Run the unified installation script:**

```bash
sudo ./install.sh
```

3. **Follow the interactive prompts:**
   - The script will detect your operating system
   - Choose your preferred installation method (1-3)
   - Answer configuration questions
   - Wait for installation to complete

## Installation Methods

### 1. Kubernetes Installation

**Best for:** Production environments, high availability, auto-scaling

**What gets installed:**
- Kubernetes cluster (or connects to existing managed K8s)
- NGINX Ingress Controller
- cert-manager for automatic SSL certificates
- Control Panel application with Laravel Octane
- MariaDB cluster with replication
- Redis for caching
- Mail services (Postfix + Dovecot)
- DNS cluster (PowerDNS)
- PHP multi-version support (8.1-8.5)
- Metrics Server for monitoring

**Installation process:**
```bash
sudo ./install.sh
# Select option 1: Kubernetes
```

**Supported Kubernetes platforms:**
- Self-managed clusters (kubeadm)
- AWS EKS (Elastic Kubernetes Service)
- Azure AKS (Azure Kubernetes Service)
- Google GKE (Google Kubernetes Engine)
- DigitalOcean Kubernetes (DOKS)

**Features:**
- ✅ Automatic horizontal and vertical pod autoscaling
- ✅ Load balancing across multiple pods
- ✅ Self-healing deployments
- ✅ Rolling updates with zero downtime
- ✅ S3-compatible storage support for persistent volumes
- ✅ Multi-node cluster support

**Post-installation:**
```bash
# Get ingress IP
kubectl get service -n ingress-nginx ingress-nginx-controller

# Check pod status
kubectl get pods -n control-panel

# View logs
kubectl logs -n control-panel -l app.kubernetes.io/name=control-panel
```

### 2. Docker Compose Installation

**Best for:** Development, testing, single-server deployments

**What gets installed:**
- Docker Engine and Docker Compose
- NGINX reverse proxy with automatic SSL
- Control Panel application
- MariaDB or PostgreSQL database
- Redis cache
- Mail services (Postfix + Dovecot)
- BIND9 DNS server
- Let's Encrypt SSL automation

**Installation process:**
```bash
sudo ./install.sh
# Select option 2: Docker Compose
# Enter your domain name
# Enter your email for Let's Encrypt
```

**Features:**
- ✅ Container isolation
- ✅ Easy local development
- ✅ Simple multi-container orchestration
- ✅ Automatic SSL certificate generation
- ✅ Volume persistence
- ✅ Network isolation

**Post-installation:**
```bash
# Check container status
docker-compose ps

# View logs
docker-compose logs -f control-panel

# Create admin user
docker-compose exec control-panel php artisan make:filament-user

# Stop containers
docker-compose down

# Start containers
docker-compose up -d
```

**Database Options:**

The Docker Compose installation supports both MariaDB (default) and PostgreSQL:

```bash
# Use MariaDB (default)
docker-compose up -d

# Use PostgreSQL instead
docker-compose --profile postgresql up -d
# Update .env: DB_CONNECTION=pgsql, DB_HOST=postgresql
```

### 3. Standalone Installation

**Best for:** Traditional server setups, environments without container support, legacy systems

**What gets installed:**
- NGINX web server
- PHP 8.3 with FPM and all required extensions
- MariaDB database server
- Redis cache server
- Composer (PHP dependency manager)
- Node.js and npm
- Certbot for SSL certificates
- Mail services (Postfix + Dovecot)
- BIND9 DNS server
- Control Panel application

**Installation process:**
```bash
sudo ./install.sh
# Select option 3: Standalone
# Enter your domain name (optional)
# Choose whether to seed database
# Choose whether to setup SSL
```

**Features:**
- ✅ No container overhead
- ✅ Direct server access
- ✅ Traditional Linux service management
- ✅ Full control over configuration
- ✅ Automatic Let's Encrypt SSL setup

**Post-installation:**
```bash
# Create admin user
cd /var/www/control-panel
php artisan make:filament-user

# View application logs
tail -f /var/www/control-panel/storage/logs/laravel.log

# Manage services
systemctl status nginx
systemctl status php8.3-fpm
systemctl status mariadb
systemctl status redis-server

# NGINX configuration
nano /etc/nginx/sites-available/control-panel
systemctl reload nginx
```

**Installation locations:**
- Application: `/var/www/control-panel`
- NGINX config: `/etc/nginx/sites-available/control-panel`
- PHP-FPM: `/etc/php/8.3/fpm/`
- Database data: `/var/lib/mysql`

## Comparison Table

| Feature | Kubernetes | Docker Compose | Standalone |
|---------|-----------|----------------|------------|
| **Difficulty** | Medium-High | Low-Medium | Low |
| **Auto-scaling** | ✅ Yes | ❌ No | ❌ No |
| **High Availability** | ✅ Multi-node | ⚠️ Limited | ❌ Single server |
| **Resource Usage** | Medium | Low | Minimal |
| **SSL Automation** | ✅ cert-manager | ✅ Let's Encrypt | ✅ Certbot |
| **Zero Downtime Updates** | ✅ Yes | ⚠️ With planning | ❌ No |
| **Best For** | Production | Development | Legacy/Simple |
| **Minimum RAM** | 4GB | 2GB | 2GB |
| **Container Overhead** | Yes | Yes | No |
| **Management Complexity** | High | Medium | Low |
| **Cloud Integration** | ✅ Excellent | ⚠️ Limited | ⚠️ Manual |

## Installation Scenarios

### Scenario 1: Production Website Hosting

**Recommended:** Kubernetes

```bash
sudo ./install.sh
# Select option 1: Kubernetes
# Configure S3 storage for persistence
# Enable horizontal pod autoscaling
```

**Why:** Auto-scaling, high availability, zero-downtime deployments

### Scenario 2: Development Environment

**Recommended:** Docker Compose

```bash
sudo ./install.sh
# Select option 2: Docker Compose
# Use localhost as domain
# Skip SSL setup
```

**Why:** Fast iteration, easy container management, minimal setup

### Scenario 3: Small Business Website

**Recommended:** Standalone or Docker Compose

```bash
sudo ./install.sh
# Select option 3: Standalone
# Configure domain and SSL
```

**Why:** Low overhead, simple management, cost-effective

### Scenario 4: Multi-tenant Hosting Platform

**Recommended:** Kubernetes

```bash
sudo ./install.sh
# Select option 1: Kubernetes
# Use managed K8s (EKS/AKS/GKE)
# Enable cluster autoscaling
```

**Why:** Resource isolation, auto-scaling per tenant, high availability

## Troubleshooting

### Common Issues

#### Installation Script Fails

```bash
# Check if running as root
sudo ./install.sh

# Check OS compatibility
cat /etc/os-release
```

#### Docker Installation Fails

```bash
# Check Docker service
systemctl status docker

# Restart Docker
systemctl restart docker

# Check Docker logs
journalctl -u docker
```

#### Kubernetes Installation Fails

```bash
# Check kubelet status
systemctl status kubelet

# Check cluster status
kubectl cluster-info

# Check pod status
kubectl get pods -n control-panel
```

#### Standalone Installation - NGINX Error

```bash
# Test NGINX config
nginx -t

# Check NGINX logs
tail -f /var/log/nginx/error.log

# Restart NGINX
systemctl restart nginx
```

#### Permission Issues

```bash
# Fix permissions (Docker)
cd /path/to/control-panel-laravel
sudo chmod -R 755 .
sudo chown -R $USER:$USER .

# Fix permissions (Standalone)
sudo chown -R www-data:www-data /var/www/control-panel
sudo chmod -R 755 /var/www/control-panel
sudo chmod -R 775 /var/www/control-panel/storage
sudo chmod -R 775 /var/www/control-panel/bootstrap/cache
```

### Getting Help

1. **Check logs:**
   - Kubernetes: `kubectl logs -n control-panel <pod-name>`
   - Docker: `docker-compose logs control-panel`
   - Standalone: `tail -f /var/www/control-panel/storage/logs/laravel.log`

2. **Check documentation:**
   - [Kubernetes Guide](KUBERNETES_INSTALLATION.md)
   - [Docker Compose Setup](../README.md#docker-deployment-legacy)
   - [Multi-deployment Guide](MULTI_DEPLOYMENT_AUTOSCALING.md)

3. **Community support:**
   - GitHub Issues: https://github.com/liberu-control-panel/control-panel-laravel/issues
   - Website: https://liberu.co.uk

## Security Considerations

### All Installation Methods

1. **Keep software updated:**
   ```bash
   # Ubuntu/Debian
   apt-get update && apt-get upgrade
   
   # RHEL/AlmaLinux
   dnf update
   ```

2. **Use strong passwords:**
   - Database passwords are auto-generated during installation
   - Store credentials securely

3. **Enable firewall:**
   ```bash
   # Ubuntu
   ufw allow 80/tcp
   ufw allow 443/tcp
   ufw enable
   
   # RHEL/AlmaLinux
   firewall-cmd --permanent --add-service=http
   firewall-cmd --permanent --add-service=https
   firewall-cmd --reload
   ```

4. **Setup SSL certificates:**
   - Kubernetes: Automatic via cert-manager
   - Docker: Automatic via Let's Encrypt companion
   - Standalone: Use Certbot (prompted during installation)

### Kubernetes-Specific

1. **Network Policies:** Isolate pod communication
2. **RBAC:** Use role-based access control
3. **Secrets Management:** Use Kubernetes secrets for credentials
4. **Pod Security:** Run as non-root user

### Standalone-Specific

1. **SELinux:** Configure SELinux contexts (RHEL/AlmaLinux)
2. **File Permissions:** Ensure proper ownership and permissions
3. **Service Hardening:** Disable unnecessary services

## Next Steps

After installation:

1. **Access the control panel:**
   - Navigate to your configured domain
   - Create an admin user account

2. **Configure services:**
   - Add servers for remote management
   - Configure DNS zones
   - Setup email domains
   - Create virtual hosts

3. **Deploy applications:**
   - WordPress auto-deployment
   - Git repository deployment
   - Custom applications

4. **Monitor resources:**
   - Check resource usage
   - Configure auto-scaling (Kubernetes)
   - Setup backups

## Additional Resources

- [Complete Documentation](../README.md)
- [Kubernetes Installation Guide](KUBERNETES_INSTALLATION.md)
- [Managed Kubernetes Setup](MANAGED_KUBERNETES_SETUP.md)
- [Multi-Deployment & Auto-Scaling](MULTI_DEPLOYMENT_AUTOSCALING.md)
- [S3 Storage Configuration](S3_STORAGE.md)
- [Security Best Practices](SECURITY.md)
- [API Documentation](API_DOCUMENTATION.md)

## License

This installation script and documentation are part of the Liberu Control Panel project, licensed under the MIT License.

## Support

For support:
- GitHub Issues: https://github.com/liberu-control-panel/control-panel-laravel/issues
- Website: https://liberu.co.uk
- Email: support@liberu.co.uk
