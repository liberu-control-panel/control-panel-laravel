# Deployment Guide

Comprehensive deployment guide for the Liberu Control Panel across different platforms.

## Table of Contents

1. [Overview](#overview)
2. [Deployment Methods](#deployment-methods)
3. [Prerequisites](#prerequisites)
4. [Quick Start](#quick-start)
5. [Detailed Guides](#detailed-guides)
6. [Post-Deployment](#post-deployment)
7. [Troubleshooting](#troubleshooting)

## Overview

Liberu Control Panel supports three deployment methods:

1. **Kubernetes** - Recommended for production, scalable deployments
2. **Docker Compose** - Ideal for development and small-scale deployments
3. **Standalone** - Traditional server installation

## Deployment Methods

### Comparison Matrix

| Feature | Kubernetes | Docker Compose | Standalone |
|---------|-----------|----------------|------------|
| **Complexity** | High | Medium | Low |
| **Scalability** | Excellent | Limited | Manual |
| **Resource Usage** | Medium-High | Medium | Low |
| **Auto-scaling** | ✅ Yes | ❌ No | ❌ No |
| **High Availability** | ✅ Yes | ⚠️ Limited | ❌ No |
| **Rollback Support** | ✅ Yes | ✅ Yes | ⚠️ Manual |
| **Best For** | Production | Development | Small deployments |
| **Minimum Servers** | 3+ nodes | 1 server | 1 server |
| **Setup Time** | 30-60 min | 10-20 min | 20-40 min |

## Prerequisites

### All Deployment Methods

- **Operating System**: Ubuntu 20.04/22.04/24.04, Debian 11/12, AlmaLinux/Rocky/RHEL 8/9/10
- **RAM**: Minimum 2GB, Recommended 4GB+
- **Disk Space**: Minimum 20GB, Recommended 50GB+
- **CPU**: Minimum 2 cores, Recommended 4+ cores
- **Network**: Public IP address (for production)
- **Domain**: Valid domain name with DNS configured

### Method-Specific

#### Kubernetes
- Kubernetes cluster (self-managed or managed: EKS, AKS, GKE, DOKS)
- kubectl configured
- Helm 3.x (optional but recommended)
- Ingress controller (NGINX Ingress recommended)
- cert-manager for SSL certificates

#### Docker Compose
- Docker Engine 20.10+
- Docker Compose Plugin or docker-compose 2.0+
- 4GB+ RAM recommended

#### Standalone
- Root or sudo access
- PHP 8.3+ with required extensions
- NGINX or Apache web server
- MySQL 8.0+/MariaDB 11.2+ or PostgreSQL 16+
- Redis 6.0+ (optional but recommended)

## Quick Start

### Kubernetes

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Run installer
sudo ./install.sh
# Select option 1: Kubernetes

# Or use Helm directly
helm install control-panel ./helm/control-panel \
  --set app.key="base64:$(openssl rand -base64 32)" \
  --set mysql.auth.password="$(openssl rand -base64 16)" \
  --namespace control-panel \
  --create-namespace
```

### Docker Compose

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Run installer
sudo ./install.sh
# Select option 2: Docker Compose

# Or manually:
# 1. Create secrets
mkdir -p secrets
openssl rand -base64 32 > secrets/db_root_password.txt
openssl rand -base64 32 > secrets/db_password.txt

# 2. Configure environment
cp .env.example .env
# Edit .env with your settings

# 3. Start services
docker-compose -f docker-compose.base.yml up -d

# 4. Run migrations
docker-compose exec control-panel php artisan migrate --seed
```

### Standalone

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Run installer
sudo ./install.sh
# Select option 3: Standalone

# Follow the interactive prompts
```

## Detailed Guides

### Kubernetes Deployment

See [docs/KUBERNETES_INSTALLATION.md](docs/KUBERNETES_INSTALLATION.md) for detailed Kubernetes setup.

#### Key Steps:

1. **Cluster Setup**
   ```bash
   # For managed Kubernetes (EKS, AKS, GKE)
   ./install-k8s.sh
   # Follow prompts for your provider
   
   # For self-managed
   ./install-k8s.sh
   # Select self-managed option
   ```

2. **Deploy Application**
   ```bash
   # Using Makefile
   make deploy-prod
   
   # Using Helm
   ./install-control-panel.sh
   
   # Using Kustomize
   kubectl apply -k k8s/overlays/production/
   ```

3. **Verify Deployment**
   ```bash
   # Check pods
   kubectl get pods -n control-panel
   
   # Check services
   kubectl get svc -n control-panel
   
   # Get ingress IP
   kubectl get ingress -n control-panel
   ```

4. **Access Application**
   - Point your domain DNS to the Ingress IP
   - Access via https://your-domain.com

#### Health Checks

The deployment includes comprehensive health check endpoints:
- `/health` - Basic health check
- `/health/live` - Liveness probe
- `/health/ready` - Readiness probe
- `/health/startup` - Startup probe
- `/health/detailed` - Detailed metrics

#### Validation

```bash
# Validate manifests before deploying
./k8s/validate.sh

# Skip cluster checks (for CI/CD)
SKIP_CLUSTER_CHECKS=true ./k8s/validate.sh
```

### Docker Compose Deployment

#### Modular Configuration

The Docker setup is split into multiple files for flexibility:

```bash
# Base services (app, database, Redis)
docker-compose.base.yml

# Additional services (mail, DNS)
docker-compose.services.yml

# Development overrides
docker-compose.dev.yml
```

#### Production Setup

```bash
# Start core services
docker-compose -f docker-compose.base.yml up -d

# Include mail and DNS services
docker-compose -f docker-compose.base.yml -f docker-compose.services.yml up -d
```

#### Development Setup

```bash
# Start with development overrides
docker-compose -f docker-compose.base.yml -f docker-compose.dev.yml up

# Includes: Hot reload, debug mode, Mailhog, phpMyAdmin
```

#### Health Monitoring

```bash
# Check container health
docker-compose ps

# View logs
docker-compose logs -f control-panel

# Execute commands
docker-compose exec control-panel php artisan tinker
```

### Standalone Deployment

#### Step-by-Step Installation

1. **Run Installer**
   ```bash
   sudo ./install.sh
   # Select option 3: Standalone
   ```

2. **Configure Services**
   
   The installer automatically:
   - Installs NGINX, PHP, MySQL/MariaDB
   - Configures PHP-FPM
   - Sets up application
   - Configures SSL with Let's Encrypt (optional)

3. **Manual Configuration (if needed)**

   **NGINX Configuration:**
   ```bash
   # Copy optimized config
   sudo cp configs/nginx/control-panel.conf /etc/nginx/sites-available/
   sudo ln -s /etc/nginx/sites-available/control-panel /etc/nginx/sites-enabled/
   
   # Update domain and paths
   sudo nano /etc/nginx/sites-available/control-panel
   
   # Test and reload
   sudo nginx -t
   sudo systemctl reload nginx
   ```

   **Firewall Setup:**
   ```bash
   # Ubuntu/Debian
   sudo ./configs/firewall/setup-ufw.sh
   
   # RHEL/AlmaLinux
   sudo ./configs/firewall/setup-firewalld.sh
   ```

   **Background Workers:**
   ```bash
   # Install systemd services
   sudo cp systemd/*.service /etc/systemd/system/
   sudo cp systemd/*.timer /etc/systemd/system/
   sudo systemctl daemon-reload
   
   # Enable and start
   sudo systemctl enable control-panel-queue control-panel-scheduler.timer
   sudo systemctl start control-panel-queue control-panel-scheduler.timer
   ```

## Post-Deployment

### 1. Create Admin User

```bash
# Kubernetes
kubectl exec -it -n control-panel deployment/control-panel -- php artisan make:filament-user

# Docker
docker-compose exec control-panel php artisan make:filament-user

# Standalone
cd /var/www/control-panel
sudo -u www-data php artisan make:filament-user
```

### 2. Configure Application

Access `/app` and configure:
- Mail settings
- Storage settings (S3, etc.)
- DNS servers
- Mail servers
- Default settings

### 3. Set Up Monitoring

```bash
# Standalone only
sudo ./scripts/monitoring/setup-monitoring.sh

# Verify monitoring
sudo systemctl status control-panel-health.timer
```

### 4. Configure Backups

```bash
# Test backup script
sudo ./scripts/backup/backup.sh

# Schedule automated backups (see scripts/backup/README.md)
sudo crontab -e
# Add: 0 2 * * * /var/www/control-panel/scripts/backup/backup.sh
```

### 5. Security Hardening

See [Security Checklist](#security-checklist) below.

## Troubleshooting

### Common Issues

#### Application Not Accessible

**Symptoms**: Can't access web interface

**Solutions**:
```bash
# Check web server
sudo systemctl status nginx

# Check PHP-FPM
sudo systemctl status php8.3-fpm

# Check logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/www/control-panel/storage/logs/laravel.log
```

#### Database Connection Failed

**Symptoms**: "Could not connect to database" errors

**Solutions**:
```bash
# Check database service
sudo systemctl status mysql  # or mariadb or postgresql

# Test connection
mysql -h localhost -u control_panel -p

# Check .env configuration
cat /var/www/control-panel/.env | grep DB_
```

#### Queue Workers Not Processing

**Symptoms**: Background jobs stuck in queue

**Solutions**:
```bash
# Check queue worker
sudo systemctl status control-panel-queue

# Restart worker
sudo systemctl restart control-panel-queue

# Check Redis
redis-cli ping
```

#### SSL Certificate Issues

**Symptoms**: "Your connection is not private" warnings

**Solutions**:
```bash
# Check certificate files
sudo ls -la /etc/letsencrypt/live/your-domain.com/

# Renew certificate
sudo certbot renew

# Check NGINX SSL configuration
sudo nginx -t
```

### Performance Issues

#### Slow Page Loads

**Diagnostics**:
```bash
# Check system resources
htop

# Check database queries
sudo tail -f /var/www/control-panel/storage/logs/laravel.log | grep "SELECT"

# Enable query logging
# Edit .env: LOG_QUERY=true
```

**Solutions**:
- Enable opcache
- Configure Redis caching
- Optimize database indices
- Enable Laravel caching
- Use CDN for static assets

#### High Memory Usage

**Diagnostics**:
```bash
# Check memory usage
free -h

# Find memory hogs
ps aux --sort=-%mem | head
```

**Solutions**:
- Adjust PHP-FPM pool settings
- Increase server RAM
- Enable swap (temporary)
- Optimize worker processes

## Security Checklist

### Essential Security Measures

- [ ] Change all default passwords
- [ ] Configure firewall (UFW/FirewallD)
- [ ] Enable SSL/TLS certificates
- [ ] Configure fail2ban for brute force protection
- [ ] Disable directory listing
- [ ] Set proper file permissions (644 files, 755 directories)
- [ ] Enable security headers (HSTS, CSP, etc.)
- [ ] Regular security updates
- [ ] Enable audit logging
- [ ] Configure backup encryption
- [ ] Implement 2FA for admin accounts

### File Permissions

```bash
# Application directory
sudo chown -R www-data:www-data /var/www/control-panel
sudo find /var/www/control-panel -type f -exec chmod 644 {} \;
sudo find /var/www/control-panel -type d -exec chmod 755 {} \;

# Storage and cache writable
sudo chmod -R 775 /var/www/control-panel/storage
sudo chmod -R 775 /var/www/control-panel/bootstrap/cache

# Protect .env
sudo chmod 600 /var/www/control-panel/.env
```

### Firewall Configuration

```bash
# Ubuntu/Debian
sudo ./configs/firewall/setup-ufw.sh

# RHEL/AlmaLinux
sudo ./configs/firewall/setup-firewalld.sh
```

## Maintenance

### Regular Tasks

**Daily**:
- Monitor health check logs
- Check backup completion
- Review error logs

**Weekly**:
- Review resource usage
- Check for security updates
- Test backup restoration

**Monthly**:
- Update dependencies
- Review access logs for anomalies
- Performance optimization review

### Updates

```bash
# Update application code
cd /var/www/control-panel
git pull origin main
composer install --no-dev
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart php8.3-fpm control-panel-queue
```

## Support and Resources

- **Documentation**: See `/docs` directory
- **GitHub Issues**: https://github.com/liberu-control-panel/control-panel-laravel/issues
- **Website**: https://liberu.co.uk
- **Community**: GitHub Discussions

## Additional Resources

- [Kubernetes Guide](docs/KUBERNETES_INSTALLATION.md)
- [Docker Guide](docs/DOCKER_INSTALLATION.md)
- [Standalone Guide](docs/STANDALONE_DEPLOYMENT.md)
- [Systemd Services](systemd/README.md)
- [Firewall Configuration](configs/firewall/README.md)
- [NGINX Configuration](configs/nginx/README.md)
- [Backup Guide](scripts/backup/README.md)
- [Monitoring Guide](scripts/monitoring/README.md)
