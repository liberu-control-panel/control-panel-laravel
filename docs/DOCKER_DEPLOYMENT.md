# Docker Deployment Guide

Complete guide for deploying Liberu Control Panel using Docker and Docker Compose.

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Quick Start](#quick-start)
4. [Configuration](#configuration)
5. [Deployment Modes](#deployment-modes)
6. [Health Checks](#health-checks)
7. [Scaling](#scaling)
8. [Troubleshooting](#troubleshooting)

## Overview

The Docker deployment uses a multi-stage Dockerfile for optimized image size and modular docker-compose files for flexibility.

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     NGINX Proxy                         │
│              (jwilder/nginx-proxy)                      │
│              + Let's Encrypt SSL                        │
└─────────────────────┬───────────────────────────────────┘
                      │
    ┌─────────────────┼─────────────────┐
    │                 │                 │
┌───▼───┐      ┌──────▼──────┐   ┌─────▼─────┐
│ App   │◄────►│   MySQL/    │   │   Redis   │
│ PHP   │      │ PostgreSQL  │   │   Cache   │
│ FPM   │      └─────────────┘   └───────────┘
└───┬───┘
    │
┌───▼────────────────────────────┐
│  Optional Services:            │
│  - Postfix (Mail)              │
│  - Dovecot (IMAP/POP3)         │
│  - BIND9 (DNS)                 │
│  - Queue Workers               │
│  - Scheduler                   │
└────────────────────────────────┘
```

## Prerequisites

### System Requirements

- **OS**: Linux (Ubuntu, Debian, CentOS, etc.), macOS, or Windows with WSL2
- **RAM**: Minimum 4GB, Recommended 8GB+
- **Disk**: Minimum 20GB free space
- **CPU**: 2+ cores recommended

### Software Requirements

- **Docker Engine**: 20.10+ ([Install Docker](https://docs.docker.com/engine/install/))
- **Docker Compose**: V2 (Docker Compose plugin) or V1.29+

### Verify Installation

```bash
docker --version
docker compose version  # or: docker-compose --version
```

## Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel
```

### 2. Create Secrets

```bash
mkdir -p secrets
openssl rand -base64 32 > secrets/db_root_password.txt
openssl rand -base64 32 > secrets/db_password.txt
chmod 600 secrets/*.txt
```

### 3. Configure Environment

```bash
cp .env.example .env

# Generate application key
APP_KEY=$(openssl rand -base64 32)

# Update .env file
nano .env
```

Edit the following variables in `.env`:
```env
APP_KEY=base64:YOUR_GENERATED_KEY
APP_URL=https://your-domain.com
CONTROL_PANEL_DOMAIN=your-domain.com
LETSENCRYPT_EMAIL=admin@your-domain.com
DB_PASSWORD_FILE=/run/secrets/db_password
REDIS_PASSWORD=changeme
```

### 4. Start Services

```bash
# Production deployment (base services only)
docker compose -f docker-compose.base.yml up -d

# Or with all services (mail, DNS, etc.)
docker compose -f docker-compose.base.yml -f docker-compose.services.yml up -d
```

### 5. Run Migrations

```bash
# Wait for database to be ready (check with docker compose ps)
docker compose -f docker-compose.base.yml exec control-panel php artisan migrate --seed
```

### 6. Create Admin User

```bash
docker compose exec control-panel php artisan make:filament-user
```

### 7. Access Application

Open your browser and navigate to:
- HTTP: `http://your-domain.com` (will redirect to HTTPS)
- HTTPS: `https://your-domain.com`

## Configuration

### Docker Compose Files

The setup uses modular compose files:

#### 1. docker-compose.base.yml

Core services:
- Control Panel application (PHP-FPM + NGINX)
- MySQL/MariaDB database
- PostgreSQL (optional, via profile)
- Redis cache
- NGINX reverse proxy
- Let's Encrypt SSL automation
- Queue worker
- Scheduler

**Usage**:
```bash
docker compose -f docker-compose.base.yml up -d
```

#### 2. docker-compose.services.yml

Additional services:
- Postfix (SMTP server)
- Dovecot (IMAP/POP3 server)
- BIND9 (DNS server)

**Usage**:
```bash
docker compose -f docker-compose.base.yml -f docker-compose.services.yml up -d
```

#### 3. docker-compose.dev.yml

Development overrides:
- Hot reload enabled
- Debug mode
- Mailhog (email testing)
- phpMyAdmin
- Source code mounted as volume

**Usage**:
```bash
docker compose -f docker-compose.base.yml -f docker-compose.dev.yml up
```

### Multi-Stage Dockerfile

The Dockerfile uses multi-stage builds for optimization:

**Benefits**:
- Smaller final image (dependencies installed in separate stage)
- Faster builds (better layer caching)
- Production-ready (only runtime dependencies included)

**Features**:
- PHP 8.1 FPM base
- Composer dependencies optimized
- Non-root user (appuser:1000)
- Health check included
- Security hardening (no root, minimal permissions)

### Environment Variables

Key environment variables:

```env
# Application
APP_NAME="Liberu Control Panel"
APP_ENV=production
APP_KEY=base64:YOUR_KEY
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD_FILE=/run/secrets/db_password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=changeme
REDIS_PORT=6379

# Proxy
CONTROL_PANEL_DOMAIN=your-domain.com
LETSENCRYPT_EMAIL=admin@your-domain.com

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

## Deployment Modes

### Production Deployment

**Features**:
- SSL/TLS enabled
- Queue workers running
- Scheduled tasks enabled
- Optimized for performance
- Persistent storage

```bash
docker compose -f docker-compose.base.yml up -d

# Scale queue workers
docker compose -f docker-compose.base.yml up -d --scale queue-worker=3
```

### Development Deployment

**Features**:
- Source code hot-reload
- Debug mode enabled
- Email testing (Mailhog)
- Database admin (phpMyAdmin)
- Non-optimized for debugging

```bash
docker compose -f docker-compose.base.yml -f docker-compose.dev.yml up
```

**Access development tools**:
- Mailhog UI: http://localhost:8025
- phpMyAdmin: http://localhost:8080

### PostgreSQL Instead of MySQL

```bash
# Start with PostgreSQL profile
docker compose --profile postgresql -f docker-compose.base.yml up -d

# Update .env
DB_CONNECTION=pgsql
DB_HOST=postgresql
DB_PORT=5432
```

## Health Checks

All services include health checks for reliability.

### Application Health

```bash
# Check container health
docker compose ps

# Manual health check
curl http://localhost/health

# Detailed health check
curl http://localhost/health/detailed
```

### Service Health Checks

```bash
# MySQL health
docker compose exec mysql mysqladmin ping

# Redis health
docker compose exec redis redis-cli ping

# PHP-FPM health
docker compose exec control-panel php -v
```

### View Health Status

```bash
# Docker native health check
docker inspect --format='{{.State.Health.Status}}' control-panel_control-panel_1

# Via docker compose
docker compose ps
```

## Scaling

### Scale Queue Workers

```bash
# Scale to 5 workers
docker compose -f docker-compose.base.yml up -d --scale queue-worker=5

# Verify
docker compose ps queue-worker
```

### Resource Limits

Edit docker-compose.base.yml to adjust resource limits:

```yaml
services:
  control-panel:
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
        reservations:
          cpus: '1.0'
          memory: 1G
```

### Load Balancing

For multiple application instances:

```bash
# Scale control-panel service
docker compose up -d --scale control-panel=3

# NGINX proxy automatically load balances
```

## Management Commands

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f control-panel

# Last 100 lines
docker compose logs --tail=100 control-panel
```

### Execute Commands

```bash
# Artisan commands
docker compose exec control-panel php artisan list

# Composer
docker compose exec control-panel composer show

# Shell access
docker compose exec control-panel bash

# Database access
docker compose exec mysql mysql -u root -p
```

### Restart Services

```bash
# Restart all
docker compose restart

# Restart specific service
docker compose restart control-panel

# Restart with rebuild
docker compose up -d --build --force-recreate
```

### Stop Services

```bash
# Stop all
docker compose stop

# Stop specific service
docker compose stop control-panel

# Stop and remove containers
docker compose down

# Stop and remove everything (including volumes)
docker compose down -v
```

## Backup and Restore

### Backup Database

```bash
# MySQL backup
docker compose exec mysql mysqldump -u root -p$(cat secrets/db_root_password.txt) myapp > backup.sql

# PostgreSQL backup
docker compose exec postgresql pg_dump -U myapp myapp > backup.sql
```

### Backup Volumes

```bash
# List volumes
docker volume ls

# Backup volume
docker run --rm -v control-panel_app-storage:/data -v $(pwd):/backup alpine tar czf /backup/storage-backup.tar.gz /data
```

### Restore Database

```bash
# MySQL restore
cat backup.sql | docker compose exec -T mysql mysql -u root -p$(cat secrets/db_root_password.txt) myapp

# PostgreSQL restore
cat backup.sql | docker compose exec -T postgresql psql -U myapp myapp
```

## Troubleshooting

### Container Won't Start

**Check logs**:
```bash
docker compose logs control-panel
```

**Common issues**:
- Port conflicts: Check if ports 80/443 are in use
- Permission issues: Check file permissions
- Missing secrets: Ensure secrets directory exists

### Database Connection Errors

**Solutions**:
```bash
# Check database is running
docker compose ps mysql

# Check database health
docker compose exec mysql mysqladmin ping -h localhost -u root -p$(cat secrets/db_root_password.txt)

# Verify environment variables
docker compose exec control-panel env | grep DB_
```

### SSL Certificate Issues

**Check certificate generation**:
```bash
# View Let's Encrypt logs
docker compose logs letsencrypt

# Manually request certificate
docker compose exec letsencrypt /app/signal_le_service
```

**Requirements for Let's Encrypt**:
- Domain must point to your server
- Ports 80 and 443 must be accessible
- Valid email address configured

### Performance Issues

**Check resource usage**:
```bash
# Container stats
docker stats

# System resources
docker system df
```

**Optimize**:
- Increase resource limits
- Enable opcache
- Configure Redis caching
- Use volume mounts carefully (can be slow on some systems)

### Network Issues

**Check networks**:
```bash
# List networks
docker network ls

# Inspect network
docker network inspect control-panel_proxy-network
```

**Rebuild network**:
```bash
docker compose down
docker compose up -d
```

## Production Best Practices

1. **Use specific image tags** instead of `latest`
2. **Set resource limits** for all services
3. **Enable monitoring** (Prometheus, Grafana)
4. **Regular backups** automated via cron
5. **Update regularly** but test in staging first
6. **Use Docker secrets** for sensitive data
7. **Enable log rotation** to prevent disk fill
8. **Monitor disk usage** especially for databases
9. **Use reverse proxy** (NGINX proxy included)
10. **Enable HTTPS** always for production

## Security Considerations

- Keep Docker updated
- Use non-root containers
- Scan images for vulnerabilities: `docker scan control-panel:latest`
- Limit container capabilities
- Use read-only filesystem where possible
- Don't expose unnecessary ports
- Use secrets for sensitive data
- Enable Docker Content Trust
- Regular security audits

## Advanced Topics

### Custom NGINX Configuration

Mount custom config:
```yaml
nginx:
  volumes:
    - ./custom-nginx.conf:/etc/nginx/conf.d/custom.conf:ro
```

### Multi-Server Deployment

Use Docker Swarm or Kubernetes for multi-server:

```bash
# Initialize swarm
docker swarm init

# Deploy stack
docker stack deploy -c docker-compose.base.yml control-panel
```

### CI/CD Integration

Example GitHub Actions workflow:

```yaml
- name: Build and push
  run: |
    docker build -t ghcr.io/liberu-control-panel/control-panel:${{ github.sha }} .
    docker push ghcr.io/liberu-control-panel/control-panel:${{ github.sha }}
```

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/)
- [Laravel Docker Best Practices](https://laravel.com/docs/deployment)
- [Docker Security](https://docs.docker.com/engine/security/)
