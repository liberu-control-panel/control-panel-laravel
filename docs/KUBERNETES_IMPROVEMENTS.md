# Kubernetes Support Improvements

## Summary

This update significantly enhances the Kubernetes support for Liberu Control Panel by adding production-ready deployment manifests, Helm charts, and comprehensive automation tools.

## What's New

### 1. Production-Ready Kubernetes Manifests

**Location**: `k8s/base/`

Complete set of Kubernetes manifests for deploying the control panel:
- Namespace configuration
- ConfigMaps for application settings
- Secrets for sensitive data
- Deployment with NGINX + PHP-FPM multi-container pods
- Services (ClusterIP)
- Ingress with TLS/SSL support
- MySQL StatefulSet for database
- Redis for caching
- PersistentVolumeClaims for storage

**Features**:
- Health checks (liveness and readiness probes)
- Security contexts (non-root, dropped capabilities)
- Resource limits and requests
- Rolling update strategy
- Multi-container pods (NGINX + PHP-FPM)

### 2. Environment-Specific Overlays

**Location**: `k8s/overlays/`

Kustomize overlays for different environments:

**Development** (`overlays/development/`):
- Single replica
- Debug mode enabled
- Development image tag
- Reduced resource requirements

**Production** (`overlays/production/`):
- 3+ replicas
- Production optimizations
- Horizontal Pod Autoscaler (2-10 pods)
- Latest stable image tag

### 3. Helm Chart

**Location**: `helm/control-panel/`

Complete Helm chart for easy deployment with customizable values:

**Key Features**:
- Parameterized configuration
- Template-driven manifests
- Values file with sensible defaults
- Auto-scaling support (HPA)
- MySQL and Redis dependencies
- Ingress with TLS
- Health checks and probes
- RBAC support

**Templates Included**:
- Deployment
- Service
- Ingress
- ConfigMap
- Secret
- PVC
- HPA
- ServiceAccount

### 4. Deployment Automation

#### Deployment Script (`k8s/deploy.sh`)
Automated deployment with:
- Pre-flight checks
- Secret management
- Ingress configuration
- Health check waiting
- Automatic migrations
- Status reporting

#### Makefile
Common operations:
```bash
make deploy-prod      # Deploy to production
make deploy-dev       # Deploy to development
make status          # Check status
make logs            # View logs
make migrate         # Run migrations
make seed            # Seed database
make shell           # Access pod shell
make helm-install    # Install via Helm
make helm-upgrade    # Upgrade Helm release
make validate        # Validate manifests
make clean           # Remove deployment
```

### 5. Comprehensive Documentation

#### Updated Documentation:
- **KUBERNETES_SETUP.md**: Complete setup guide with deployment options
- **k8s/README.md**: Kubernetes manifests reference
- **helm/control-panel/README.md**: Helm chart documentation
- **README.md**: Enhanced with deployment architecture and commands

#### Documentation Includes:
- Three deployment methods (Helm, Kustomize, Makefile)
- Post-deployment steps
- Configuration reference
- Troubleshooting guides
- Security best practices
- Maintenance procedures
- Architecture diagrams

## Deployment Options

### Option 1: Helm (Recommended)

```bash
helm install control-panel ./helm/control-panel \
  --set app.key="base64:YOUR_KEY" \
  --set mysql.auth.password="secure-password" \
  --set mysql.auth.rootPassword="secure-root-password" \
  --namespace control-panel \
  --create-namespace
```

### Option 2: Kustomize

```bash
export APP_KEY="base64:YOUR_KEY"
export DB_PASSWORD="secure-password"
export DOMAIN="control.yourdomain.com"
./k8s/deploy.sh
```

### Option 3: Makefile

```bash
make deploy-prod
make migrate
```

## Architecture

### Control Panel on Kubernetes

```
Ingress (TLS) → Service → Deployment (NGINX + PHP-FPM)
                              ↓
                    ┌─────────┴─────────┐
                    │                   │
                  Redis              MySQL
                (Cache)           (StatefulSet)
```

### Managing Remote Clusters

The control panel can manage multiple remote Kubernetes clusters via SSH for deploying customer applications with:
- Namespace isolation
- RBAC policies
- Network policies
- Resource quotas
- Automatic TLS certificates

## Security Features

1. **Pod Security**:
   - Run as non-root user
   - Read-only root filesystem (optional)
   - Dropped capabilities
   - Security contexts

2. **Network Security**:
   - Ingress with TLS/SSL
   - Network policies support
   - Service mesh ready

3. **Secret Management**:
   - Kubernetes secrets for sensitive data
   - Environment-based configuration
   - No hardcoded credentials

4. **Resource Controls**:
   - CPU and memory limits
   - Resource quotas
   - Horizontal pod autoscaling

## Testing & Validation

All manifests have been validated:
- ✅ YAML syntax validation
- ✅ Helm chart linting
- ✅ Template rendering tests
- ✅ Kustomize build validation

## Migration Guide

### From Docker to Kubernetes

If you're currently running via Docker Compose:

1. Export your database
2. Deploy to Kubernetes using Helm
3. Import your database
4. Update DNS to point to Ingress
5. Verify functionality

### Existing Kubernetes Deployments

If you have custom Kubernetes manifests:

1. Review the new manifests in `k8s/base/`
2. Customize via Kustomize overlays or Helm values
3. Test in development environment
4. Deploy to production

## Benefits

1. **Production-Ready**: Enterprise-grade deployment configuration
2. **Scalable**: Horizontal pod autoscaling support
3. **Automated**: One-command deployment
4. **Flexible**: Multiple deployment options (Helm, Kustomize, Makefile)
5. **Documented**: Comprehensive guides and references
6. **Secure**: Built-in security best practices
7. **Maintainable**: Easy updates and rollbacks

## Future Enhancements

Potential future improvements:
- GitOps integration (ArgoCD/Flux)
- Service mesh integration (Istio/Linkerd)
- Advanced monitoring (Prometheus/Grafana)
- Backup automation
- Multi-cluster federation
- CI/CD pipeline templates

## Support

- **Documentation**: See `docs/KUBERNETES_SETUP.md`
- **Issues**: https://github.com/liberu-control-panel/control-panel-laravel/issues
- **Discussions**: GitHub Discussions
