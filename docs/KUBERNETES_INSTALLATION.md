# Complete Kubernetes Installation Guide

This guide covers the complete installation and setup of the Liberu Control Panel with full Kubernetes support, including all services and infrastructure components.

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Installation Steps](#installation-steps)
4. [Services Included](#services-included)
5. [Configuration](#configuration)
6. [Post-Installation](#post-installation)
7. [Management](#management)
8. [Troubleshooting](#troubleshooting)

## Overview

This installation provides a complete, production-ready hosting control panel on Kubernetes with:

- **Kubernetes Cluster Setup**: Automated installation on Ubuntu LTS and AlmaLinux/RHEL
- **Control Panel**: Laravel-based web interface with Filament admin panel
- **NGINX Ingress**: With automatic Let's Encrypt SSL certificates
- **Database**: MariaDB cluster with multi-user support
- **Caching**: Redis for session and cache management
- **Mail Services**: Postfix (SMTP) and Dovecot (IMAP/POP3)
- **DNS Cluster**: PowerDNS with multiple nameservers
- **PHP Multi-Version**: Support for PHP 8.1, 8.2, 8.3, 8.4, and 8.5
- **Laravel Services**: Queue workers, scheduler, and Octane support

## Prerequisites

### Hardware Requirements

**Master Node (Control Plane):**
- 4 CPU cores minimum (8 recommended)
- 8GB RAM minimum (16GB recommended)
- 100GB disk space minimum
- Static IP address

**Worker Nodes:**
- 2 CPU cores minimum (4 recommended)
- 4GB RAM minimum (8GB recommended)
- 50GB disk space minimum

### Software Requirements

- **Operating System**: Ubuntu LTS (20.04/22.04/24.04) or AlmaLinux/RHEL 8/9
- **Root access** or sudo privileges
- **Network**: Internet connectivity for downloading packages
- **Ports**: Required ports open (see [Port Requirements](#port-requirements))

### Port Requirements

**Master Node:**
- 6443: Kubernetes API server
- 2379-2380: etcd server client API
- 10250: Kubelet API
- 10259: kube-scheduler
- 10257: kube-controller-manager
- 80, 443: HTTP/HTTPS (Ingress)
- 53: DNS (TCP/UDP)
- 25, 587: SMTP
- 143, 993: IMAP
- 110, 995: POP3

**Worker Nodes:**
- 10250: Kubelet API
- 30000-32767: NodePort Services

## Installation Steps

### Step 1: Install Kubernetes Cluster

The `install-k8s.sh` script automates the installation of a Kubernetes cluster on Ubuntu LTS or AlmaLinux/RHEL.

#### For Master Node:

```bash
# Clone the repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Set environment variables
export K8S_VERSION=1.29
export POD_NETWORK_CIDR=10.244.0.0/16
export LETSENCRYPT_EMAIL=your-email@example.com
export NODE_TYPE=master

# Run installation
sudo ./install-k8s.sh
```

The script will:
1. Detect your OS (Ubuntu or AlmaLinux/RHEL)
2. Install container runtime (containerd)
3. Install Kubernetes components (kubelet, kubeadm, kubectl)
4. Initialize the Kubernetes cluster
5. Install Calico CNI for pod networking
6. Install NGINX Ingress Controller
7. Install cert-manager for Let's Encrypt
8. Install Metrics Server

**Save the join command** displayed at the end. You'll need it for worker nodes.

#### For Worker Nodes:

```bash
# Clone the repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Set environment variables
export K8S_VERSION=1.29
export NODE_TYPE=worker
export JOIN_COMMAND="kubeadm join <master-ip>:6443 --token <token> --discovery-token-ca-cert-hash sha256:<hash>"

# Run installation
sudo ./install-k8s.sh
```

### Step 2: Install Control Panel and Services

After the Kubernetes cluster is ready, install the control panel and all services:

```bash
# Set configuration
export NAMESPACE=control-panel
export DOMAIN=control.yourdomain.com
export LETSENCRYPT_EMAIL=admin@yourdomain.com
export DB_ROOT_PASSWORD=$(openssl rand -base64 32)
export DB_PASSWORD=$(openssl rand -base64 32)

# Run installation
./install-control-panel.sh
```

This will install:
- MariaDB cluster (3 nodes)
- Redis cache
- Control Panel (Laravel application)
- Mail services (Postfix + Dovecot)
- DNS cluster (PowerDNS)
- PHP multi-version support (8.1-8.5)

### Step 3: Configure DNS

Point your domain to the Ingress controller's external IP:

```bash
# Get Ingress IP
kubectl get svc -n ingress-nginx ingress-nginx-controller

# Create DNS A record
# control.yourdomain.com -> <EXTERNAL-IP>
```

### Step 4: Access Control Panel

1. Wait for Let's Encrypt certificate (may take 2-5 minutes)
2. Access https://control.yourdomain.com
3. Log in with the admin credentials you created during installation

## Services Included

### 1. Control Panel (Laravel Application)

- **Technology**: Laravel 12 + Filament 5.2
- **Features**: 
  - Web hosting management
  - Virtual host configuration
  - Database management
  - Mail domain management
  - DNS zone management
  - Docker/Kubernetes orchestration
- **Access**: Main domain (e.g., https://control.yourdomain.com)

### 2. NGINX Ingress Controller

- **Purpose**: Routes external traffic to services
- **Features**:
  - Automatic SSL/TLS with Let's Encrypt
  - Virtual host support
  - Load balancing
  - Rate limiting
- **Configuration**: Via Ingress resources

### 3. MariaDB Cluster

- **Architecture**: Primary + 2 replicas
- **Features**:
  - High availability
  - Automatic failover
  - Multi-user support
  - Persistent storage
- **Access**: Internal service at `mariadb.control-panel.svc.cluster.local:3306`

### 4. Redis Cache

- **Purpose**: Session storage and caching
- **Architecture**: Master + 2 replicas
- **Access**: Internal service at `redis-master.control-panel.svc.cluster.local:6379`

### 5. Mail Services

#### Postfix (SMTP)
- **Purpose**: Outgoing email
- **Ports**: 587 (submission)
- **Features**:
  - TLS encryption
  - Relay support
  - Anti-spam integration

#### Dovecot (IMAP/POP3)
- **Purpose**: Incoming email
- **Ports**: 
  - 143 (IMAP)
  - 993 (IMAPS)
  - 110 (POP3)
  - 995 (POP3S)
- **Features**:
  - TLS encryption
  - Multiple mailbox formats
  - Quota management

### 6. DNS Cluster

#### PowerDNS
- **Purpose**: Authoritative DNS server
- **Features**:
  - Master/slave replication
  - MySQL backend
  - DNSSEC support (optional)
  - REST API
- **Ports**: 53 (TCP/UDP)

#### PowerDNS Admin (Optional)
- **Purpose**: Web interface for DNS management
- **Access**: https://dns-admin.yourdomain.com

### 7. PHP Multi-Version Support

Supports PHP versions 8.1, 8.2, 8.3, 8.4, and 8.5 simultaneously:

- **Architecture**: Separate PHP-FPM deployment per version
- **Selection**: Configured per virtual host
- **Services**:
  - php-versions-8-1:9000
  - php-versions-8-2:9000
  - php-versions-8-3:9000
  - php-versions-8-4:9000
  - php-versions-8-5:9000

### 8. Laravel Services

#### Queue Workers
- **Purpose**: Process background jobs
- **Queues**: default, emails, deployments
- **Replicas**: 3 workers
- **Auto-restart**: On failure

#### Scheduler
- **Purpose**: Run scheduled tasks
- **Schedule**: Every minute (Laravel scheduler)
- **Type**: Kubernetes CronJob

#### Laravel Octane (Optional)
- **Purpose**: High-performance application server
- **Server**: RoadRunner or Swoole
- **Features**:
  - Faster response times
  - Lower resource usage
  - WebSocket support

## Configuration

### Environment Variables

Key environment variables for configuration:

```bash
# Kubernetes
K8S_VERSION=1.29
POD_NETWORK_CIDR=10.244.0.0/16
SERVICE_CIDR=10.96.0.0/12

# Application
NAMESPACE=control-panel
DOMAIN=control.yourdomain.com
LETSENCRYPT_EMAIL=admin@yourdomain.com

# Database
DB_ROOT_PASSWORD=<secure-password>
DB_PASSWORD=<secure-password>

# Application Key
APP_KEY=<generated-key>
```

### Helm Values

Customize installations by creating a `values-custom.yaml`:

```yaml
# Control Panel
replicaCount: 3
app:
  url: https://control.yourdomain.com
ingress:
  hosts:
    - host: control.yourdomain.com

# Enable Octane
octane:
  enabled: true
  replicaCount: 3

# Queue configuration
queue:
  enabled: true
  replicaCount: 5
```

Apply with:
```bash
helm upgrade control-panel ./helm/control-panel -f values-custom.yaml
```

### PHP Version Selection

Configure PHP version per virtual host in the control panel UI or via configuration:

```yaml
virtualHosts:
  - domain: example.com
    phpVersion: "8.3"
    phpService: php-versions-8-3
  - domain: legacy.com
    phpVersion: "8.1"
    phpService: php-versions-8-1
```

## Post-Installation

### 1. Create Admin User

```bash
kubectl exec -n control-panel deployment/control-panel -c php-fpm -it -- php artisan make:filament-user
```

### 2. Configure Mail Relay (if needed)

If using an external mail relay:

```bash
helm upgrade mail-services ./helm/mail-services \
  --set postfix.config.relayHost=smtp.sendgrid.net \
  --set postfix.config.relayPort=587 \
  --set postfix.config.relayUser=apikey \
  --set postfix.config.relayPassword=<api-key>
```

### 3. Set Up DNS Nameservers

Configure your domain registrar to use your PowerDNS servers:

```
ns1.yourdomain.com -> <INGRESS-IP>
ns2.yourdomain.com -> <INGRESS-IP>
```

### 4. Enable Monitoring (Optional)

Install Prometheus and Grafana for monitoring:

```bash
# Add Prometheus repo
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts

# Install Prometheus
helm install prometheus prometheus-community/kube-prometheus-stack -n monitoring --create-namespace
```

## Management

### Common Operations

#### View Logs

```bash
# Application logs
kubectl logs -n control-panel deployment/control-panel -c php-fpm -f

# Queue worker logs
kubectl logs -n control-panel deployment/control-panel-queue -f

# NGINX logs
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller -f
```

#### Scale Services

```bash
# Scale application
kubectl scale deployment control-panel -n control-panel --replicas=5

# Scale queue workers
kubectl scale deployment control-panel-queue -n control-panel --replicas=10
```

#### Update Application

```bash
# Pull latest image
kubectl set image deployment/control-panel -n control-panel \
  php-fpm=ghcr.io/liberu-control-panel/control-panel-laravel:latest

# Or use Helm
helm upgrade control-panel ./helm/control-panel \
  --set image.tag=v2.0.0
```

#### Backup Database

```bash
# Create backup
kubectl exec -n control-panel statefulset/mariadb-primary -- \
  mysqldump -u root -p$DB_ROOT_PASSWORD --all-databases > backup.sql

# Restore backup
kubectl exec -i -n control-panel statefulset/mariadb-primary -- \
  mysql -u root -p$DB_ROOT_PASSWORD < backup.sql
```

### Maintenance Mode

```bash
# Enable maintenance mode
kubectl exec -n control-panel deployment/control-panel -c php-fpm -- \
  php artisan down

# Disable maintenance mode
kubectl exec -n control-panel deployment/control-panel -c php-fpm -- \
  php artisan up
```

## Troubleshooting

### Pods Not Starting

```bash
# Check pod status
kubectl get pods -n control-panel

# Describe pod
kubectl describe pod <pod-name> -n control-panel

# Check events
kubectl get events -n control-panel --sort-by='.lastTimestamp'
```

### Certificate Issues

```bash
# Check certificate status
kubectl get certificate -n control-panel

# Check cert-manager logs
kubectl logs -n cert-manager deployment/cert-manager

# Manually trigger certificate renewal
kubectl delete certificate control-panel-tls -n control-panel
```

### Database Connection Issues

```bash
# Test database connection
kubectl exec -n control-panel deployment/control-panel -c php-fpm -- \
  php artisan tinker --execute="DB::connection()->getPdo();"

# Check MariaDB logs
kubectl logs -n control-panel statefulset/mariadb-primary
```

### Mail Not Sending

```bash
# Check Postfix logs
kubectl logs -n control-panel deployment/mail-services-postfix

# Test mail sending
kubectl exec -n control-panel deployment/mail-services-postfix -- \
  echo "Test" | mail -s "Test" test@example.com
```

### DNS Issues

```bash
# Check PowerDNS logs
kubectl logs -n control-panel deployment/dns-cluster-powerdns

# Test DNS resolution
dig @<INGRESS-IP> yourdomain.com
```

## Security Best Practices

1. **Use strong passwords** for all services
2. **Enable RBAC** and limit permissions
3. **Use NetworkPolicies** to restrict pod communication
4. **Regular updates** of all components
5. **Enable audit logging** for Kubernetes
6. **Use secrets** for sensitive data, never hardcode
7. **Regular backups** of databases and persistent volumes
8. **Monitor logs** for suspicious activity
9. **Use TLS** for all external communications
10. **Implement resource quotas** to prevent abuse

## Support and Resources

- **Documentation**: https://github.com/liberu-control-panel/control-panel-laravel/tree/main/docs
- **Issues**: https://github.com/liberu-control-panel/control-panel-laravel/issues
- **Website**: https://liberu.co.uk
- **Community**: GitHub Discussions

## License

MIT License - See LICENSE file for details
