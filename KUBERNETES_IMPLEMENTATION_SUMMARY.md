# Kubernetes Installation System - Summary

## Overview

This implementation adds comprehensive Kubernetes support to the Liberu Control Panel, providing automated installation, management, and deployment capabilities for a complete hosting infrastructure.

## What Was Implemented

### 1. Installation Scripts

#### `install-k8s.sh`
Automated Kubernetes cluster installation script supporting:
- **Operating Systems**: Ubuntu LTS (20.04, 22.04, 24.04), Debian (11, 12), and AlmaLinux/RHEL 8/9
- **Node Types**: Automatic detection and configuration for master or worker nodes
- **Components Installed**:
  - Containerd runtime
  - Kubernetes components (kubelet, kubeadm, kubectl)
  - Calico CNI for networking
  - NGINX Ingress Controller
  - cert-manager for Let's Encrypt
  - Metrics Server for monitoring

#### `install-control-panel.sh`
Complete control panel and services installation:
- MariaDB cluster with replication
- Redis for caching
- Control Panel (Laravel application)
- Mail services (Postfix + Dovecot)
- DNS cluster (PowerDNS)
- PHP multi-version support (8.1-8.5)

### 2. Helm Charts

#### Mail Services (`helm/mail-services/`)
**Components:**
- Postfix: SMTP server for sending emails
- Dovecot: IMAP/POP3 server for receiving emails

**Features:**
- High availability with 2+ replicas
- Persistent storage for mail data
- TLS/SSL support via cert-manager
- Optional relay configuration
- Health checks and monitoring

**Services Exposed:**
- SMTP: Port 587
- IMAP: Port 143/993 (TLS)
- POP3: Port 110/995 (TLS)

#### DNS Cluster (`helm/dns-cluster/`)
**Components:**
- PowerDNS: Authoritative DNS server
- PowerDNS Admin: Web interface (optional)

**Features:**
- MySQL backend for zone storage
- Master/slave replication support
- REST API for management
- High availability with 3+ replicas
- Optional DNSSEC support

**Services Exposed:**
- DNS: Port 53 (TCP/UDP)
- API: Port 8081 (internal)

#### PHP Multi-Version (`helm/php-versions/`)
**Components:**
- Separate PHP-FPM deployments for versions 8.1, 8.2, 8.3, 8.4, and 8.5

**Features:**
- Independent scaling per version
- Configurable PHP settings (memory, execution time, etc.)
- Opcache optimization
- Configurable disabled functions for security
- Health checks per version

**Services:**
Each version exposed on port 9000:
- `php-versions-8-1:9000`
- `php-versions-8-2:9000`
- `php-versions-8-3:9000`
- `php-versions-8-4:9000`
- `php-versions-8-5:9000`

### 3. Enhanced Control Panel Chart

#### New Components
**Laravel Octane Support** (`templates/octane-deployment.yaml`):
- High-performance application server
- Choice of RoadRunner or Swoole
- Configurable workers and task workers
- Auto-scaling support

**Queue Workers** (`templates/queue-deployment.yaml`):
- Background job processing
- Multiple queue support (default, emails, deployments)
- Configurable retries and timeouts
- Health monitoring

**Scheduler** (`templates/scheduler-cronjob.yaml`):
- Kubernetes CronJob for Laravel scheduler
- Runs every minute
- Job history tracking

#### Updated Values
Added configuration sections for:
- Octane settings (server, workers, max requests)
- Queue worker configuration (replicas, queues, timeouts)
- Scheduler settings

### 4. GUI Helm Chart Installer

#### Backend Services
**HelmChartService** (`app/Services/HelmChartService.php`):
- Chart discovery and management
- Helm installation/upgrade/uninstall
- Status monitoring
- Value template generation
- Support for 9 chart types (MariaDB, Redis, PostgreSQL, MongoDB, RabbitMQ, Elasticsearch, Mail, DNS, PHP)

**HelmRelease Model** (`app/Models/HelmRelease.php`):
- Database tracking of installed charts
- Status management (pending, deployed, failed, uninstalled)
- Server relationship
- Configuration storage

**Database Migration** (`database/migrations/2024_02_15_000001_create_helm_releases_table.php`):
- Helm releases tracking table
- Unique constraint per server/release/namespace
- JSON storage for values
- Timestamp tracking

#### Frontend (Filament)
**HelmReleaseResource** (`app/Filament/App/Resources/HelmReleaseResource.php`):
- Complete CRUD interface
- Chart installation wizard
- Status monitoring
- One-click upgrades
- Bulk operations

**Pages:**
- **ListHelmReleases**: Overview with statistics widget
- **CreateHelmRelease**: Installation wizard with value configuration
- **EditHelmRelease**: Modify and upgrade releases

**Widget:**
- **HelmStatsWidget**: Dashboard showing total, deployed, failed, and pending releases

### 5. Documentation

#### Installation Guide (`docs/KUBERNETES_INSTALLATION.md`)
Comprehensive 300+ line guide covering:
- Prerequisites and requirements
- Step-by-step installation
- Service descriptions
- Configuration examples
- Management commands
- Troubleshooting
- Security best practices

#### GUI Installer Guide (`docs/HELM_GUI_INSTALLER.md`)
Complete usage documentation:
- Feature overview
- Installation workflow
- Configuration examples per service
- Virtual host integration
- Automation features
- API integration

#### Chart-Specific Documentation
- `helm/mail-services/README.md`: Mail services setup
- `helm/dns-cluster/README.md`: DNS cluster configuration
- `helm/php-versions/README.md`: PHP multi-version usage

#### Updated Main README
Enhanced with:
- Quick start for complete installation
- Reference to all new documentation
- Clear installation options

## Technical Architecture

### Installation Flow
```
User → install-k8s.sh → Kubernetes Cluster
     → install-control-panel.sh → All Services
     → GUI → Helm Chart Installer → Additional Services
```

### Service Architecture
```
NGINX Ingress (Let's Encrypt)
    ↓
Control Panel (Laravel + Octane)
    ↓
├── MariaDB Cluster (HA)
├── Redis (Cache)
├── Queue Workers
├── Scheduler
└── Services
    ├── Mail (Postfix + Dovecot)
    ├── DNS (PowerDNS)
    └── PHP (8.1-8.5)
```

## Key Features

### High Availability
- Database replication (MariaDB/PostgreSQL)
- Redis clustering
- Multiple replicas for all services
- Health checks and auto-restart
- Horizontal pod autoscaling

### Security
- TLS/SSL everywhere via cert-manager
- RBAC for Kubernetes access
- Pod security contexts
- Network policies support
- Configurable PHP disabled functions
- Secret management

### Scalability
- Kubernetes native autoscaling
- Independent service scaling
- Resource limits and requests
- Efficient resource utilization
- **S3-compatible storage for persistent volumes**
  - AWS S3, MinIO, DigitalOcean Spaces, Backblaze B2, Cloudflare R2
  - Automatic scaling without capacity planning
  - Cross-node data accessibility
  - High durability (11 nines)

### Monitoring
- Health checks (liveness/readiness)
- Metrics Server integration
- Status tracking in GUI
- Resource monitoring
- Log aggregation ready

### Storage Options
- **S3-Compatible Storage**: Scalable object storage for persistent volumes
  - Automatic configuration via installation script
  - Support for multiple S3-compatible providers
  - Seamless integration with Helm charts
  - Secure credential management via Kubernetes secrets
- **Traditional Storage**: Standard Kubernetes persistent volumes
  - Local storage
  - Network-attached storage (NFS, iSCSI)
  - Cloud provider storage classes (EBS, GCE PD, Azure Disk)

### User Experience
- One-click installations
- Pre-configured templates
- Visual status monitoring
- Upgrade management
- Bulk operations
- Comprehensive documentation
- **Interactive S3 storage configuration**

## Production Readiness

### Code Quality
- ✅ Code review completed and addressed
- ✅ Security scan passed (CodeQL)
- ✅ Proper error handling
- ✅ Specific image versions
- ✅ Configurable security settings

### Testing
- ✅ Helm chart validation
- ✅ Kustomize build validation
- ✅ YAML syntax validation
- Manual testing recommended for:
  - Installation scripts on Ubuntu LTS
  - Installation scripts on Debian
  - Installation scripts on AlmaLinux/RHEL
  - GUI chart installer
  - Virtual host integration

### Documentation
- ✅ Installation guides
- ✅ Configuration references
- ✅ Usage examples
- ✅ Troubleshooting guides
- ✅ API documentation

## Files Changed/Created

### Installation Scripts (2 files)
- `install-k8s.sh` (new, 400+ lines)
- `install-control-panel.sh` (new, 300+ lines)

### Helm Charts (15 files)
**Mail Services:**
- `helm/mail-services/Chart.yaml`
- `helm/mail-services/values.yaml`
- `helm/mail-services/README.md`
- `helm/mail-services/templates/_helpers.tpl`
- `helm/mail-services/templates/postfix.yaml`
- `helm/mail-services/templates/dovecot.yaml`

**DNS Cluster:**
- `helm/dns-cluster/Chart.yaml`
- `helm/dns-cluster/values.yaml`
- `helm/dns-cluster/README.md`
- `helm/dns-cluster/templates/_helpers.tpl`
- `helm/dns-cluster/templates/powerdns.yaml`

**PHP Versions:**
- `helm/php-versions/Chart.yaml`
- `helm/php-versions/values.yaml`
- `helm/php-versions/README.md`
- `helm/php-versions/templates/_helpers.tpl`
- `helm/php-versions/templates/deployments.yaml`

**Control Panel Updates:**
- `helm/control-panel/values.yaml` (modified)
- `helm/control-panel/templates/octane-deployment.yaml` (new)
- `helm/control-panel/templates/queue-deployment.yaml` (new)
- `helm/control-panel/templates/scheduler-cronjob.yaml` (new)

### Backend Code (9 files)
- `app/Services/HelmChartService.php` (new, 400+ lines)
- `app/Models/HelmRelease.php` (new)
- `database/migrations/2024_02_15_000001_create_helm_releases_table.php` (new)
- `app/Filament/App/Resources/HelmReleaseResource.php` (new, 300+ lines)
- `app/Filament/App/Resources/HelmReleaseResource/Pages/ListHelmReleases.php` (new)
- `app/Filament/App/Resources/HelmReleaseResource/Pages/CreateHelmRelease.php` (new)
- `app/Filament/App/Resources/HelmReleaseResource/Pages/EditHelmRelease.php` (new)
- `app/Filament/App/Resources/HelmReleaseResource/Widgets/HelmStatsWidget.php` (new)

### Documentation (4 files)
- `docs/KUBERNETES_INSTALLATION.md` (new, 500+ lines)
- `docs/HELM_GUI_INSTALLER.md` (new, 300+ lines)
- `README.md` (modified)
- Multiple chart-specific READMEs

**Total: 37 files created/modified**

## Usage Example

### Complete Installation from Scratch

```bash
# 1. Install Kubernetes cluster
sudo ./install-k8s.sh

# 2. Install control panel and services
./install-control-panel.sh

# 3. Access GUI at https://control.yourdomain.com
# 4. Navigate to Kubernetes → Helm Charts
# 5. Click "Install Chart" to add more services
```

### GUI Chart Installation

```yaml
# Example: Install MongoDB
Release Name: production-mongo
Chart: MongoDB
Namespace: databases
Values:
  architecture: replicaset
  replicaCount: 3
  persistence.size: 50Gi
  auth.rootPassword: secure-password
```

## Benefits

1. **Reduced Deployment Time**: From hours to minutes
2. **Consistent Installations**: Reproducible deployments
3. **Easy Management**: GUI-based service management
4. **Scalability**: Native Kubernetes scaling
5. **High Availability**: Built-in redundancy
6. **Security**: Best practices implemented
7. **Flexibility**: Support for multiple services
8. **Documentation**: Comprehensive guides

## Future Enhancements

Potential improvements for future versions:
- GitOps integration (ArgoCD/Flux)
- Advanced monitoring (Prometheus/Grafana dashboards)
- Backup automation
- Multi-cluster federation
- CI/CD pipeline templates
- Custom chart repository
- Helm chart version management
- Rollback capabilities in GUI
- Resource usage analytics
- Cost optimization recommendations

## Support

- **Issues**: GitHub Issues
- **Documentation**: docs/ directory
- **Community**: GitHub Discussions
- **Website**: https://liberu.co.uk

## License

MIT License - See LICENSE file
