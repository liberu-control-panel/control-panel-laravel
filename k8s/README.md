# Kubernetes Deployment Manifests

This directory contains Kubernetes manifests for deploying the Liberu Control Panel.

## Directory Structure

```
k8s/
├── base/                      # Base manifests
│   ├── namespace.yaml         # Namespace definition
│   ├── configmap.yaml         # Application configuration
│   ├── secret.yaml            # Secrets (credentials)
│   ├── deployment.yaml        # Main application deployment
│   ├── service.yaml           # Service definition
│   ├── ingress.yaml           # Ingress for external access
│   ├── pvc.yaml              # Persistent storage
│   ├── mysql-statefulset.yaml # MySQL database
│   ├── mysql-service.yaml     # MySQL service
│   ├── redis.yaml            # Redis cache
│   └── kustomization.yaml    # Kustomize base config
├── overlays/
│   ├── development/          # Development environment
│   │   └── kustomization.yaml
│   └── production/           # Production environment
│       ├── kustomization.yaml
│       └── hpa.yaml          # Horizontal Pod Autoscaler
└── deploy.sh                 # Deployment script
```

## Quick Start

### Prerequisites

1. Kubernetes cluster (v1.20+)
2. kubectl CLI tool
3. NGINX Ingress Controller installed
4. cert-manager installed (for automatic SSL)
5. Storage provisioner configured

### Deployment Options

#### Option 1: Using the Deployment Script (Recommended)

```bash
# Set required environment variables
export APP_KEY="base64:YOUR_GENERATED_APP_KEY"
export DB_PASSWORD="your-secure-password"
export DB_ROOT_PASSWORD="your-secure-root-password"
export DOMAIN="control-panel.yourdomain.com"
export ENVIRONMENT="production"  # or "development"

# Run deployment
./k8s/deploy.sh
```

#### Option 2: Using kubectl with Kustomize

```bash
# Update secrets in k8s/base/secret.yaml first!

# Deploy to production
kubectl apply -k k8s/overlays/production

# Or deploy to development
kubectl apply -k k8s/overlays/development
```

#### Option 3: Using Helm Chart

See [helm/control-panel/README.md](../helm/control-panel/README.md) for Helm deployment instructions.

## Configuration

### Required Secrets

Before deploying, you must configure these secrets in `k8s/base/secret.yaml`:

1. **APP_KEY**: Generate with `php artisan key:generate --show`
2. **DB_PASSWORD**: Database user password
3. **DB_ROOT_PASSWORD**: MySQL root password

### Domain Configuration

Update the domain in `k8s/base/ingress.yaml`:

```yaml
spec:
  tls:
  - hosts:
    - your-domain.com  # Change this
  rules:
  - host: your-domain.com  # Change this
```

### Environment-Specific Configuration

#### Development

- 1 replica
- Debug mode enabled
- Development image tag
- No HPA

#### Production

- 3 replicas (minimum)
- Debug mode disabled
- Latest stable image tag
- Horizontal Pod Autoscaler (2-10 pods)

## Post-Deployment

### 1. Verify Deployment

```bash
# Check pods
kubectl get pods -n control-panel

# Check services
kubectl get svc -n control-panel

# Check ingress
kubectl get ingress -n control-panel
```

### 2. Run Migrations

```bash
POD=$(kubectl get pods -n control-panel -l app=control-panel -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n control-panel $POD -c php-fpm -- php artisan migrate --force
```

### 3. Create Admin User

```bash
kubectl exec -n control-panel $POD -c php-fpm -- php artisan make:filament-user
```

### 4. Seed Database (Optional)

```bash
kubectl exec -n control-panel $POD -c php-fpm -- php artisan db:seed
```

## Maintenance

### Viewing Logs

```bash
# Application logs
kubectl logs -n control-panel -l app=control-panel -c php-fpm -f

# NGINX logs
kubectl logs -n control-panel -l app=control-panel -c nginx -f

# Database logs
kubectl logs -n control-panel -l component=database -f
```

### Scaling

```bash
# Manual scaling (when HPA is disabled)
kubectl scale deployment control-panel -n control-panel --replicas=5

# Check HPA status
kubectl get hpa -n control-panel
```

### Updating the Application

```bash
# Update image tag in kustomization.yaml, then:
kubectl apply -k k8s/overlays/production

# Or use deployment script with new image
export IMAGE_TAG="v2.0.0"
./k8s/deploy.sh
```

### Database Backup

```bash
POD=$(kubectl get pods -n control-panel -l component=database -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n control-panel $POD -- mysqldump -u root -p${DB_ROOT_PASSWORD} controlpanel > backup.sql
```

### Accessing the Database

```bash
kubectl exec -it -n control-panel control-panel-mysql-0 -- mysql -u root -p
```

## Troubleshooting

### Pods Not Starting

```bash
# Check pod status
kubectl describe pod -n control-panel <pod-name>

# Check events
kubectl get events -n control-panel --sort-by='.lastTimestamp'
```

### Database Connection Issues

```bash
# Test database connectivity
kubectl exec -n control-panel $POD -c php-fpm -- php artisan tinker --execute="DB::connection()->getPdo();"
```

### Ingress Not Working

```bash
# Check ingress
kubectl describe ingress -n control-panel control-panel

# Check cert-manager certificate
kubectl get certificate -n control-panel

# Check NGINX ingress logs
kubectl logs -n ingress-nginx -l app.kubernetes.io/component=controller
```

### Storage Issues

```bash
# Check PVC status
kubectl get pvc -n control-panel

# Check PV
kubectl get pv
```

## Cleanup

To completely remove the deployment:

```bash
# Delete namespace (removes all resources)
kubectl delete namespace control-panel

# Or delete specific deployment
kubectl delete -k k8s/overlays/production
```

## Security Notes

1. **Always use secrets** for sensitive data (never commit real passwords to git)
2. **Enable network policies** for namespace isolation
3. **Use TLS** for all external traffic
4. **Limit resource quotas** to prevent resource exhaustion
5. **Run as non-root** user (already configured)
6. **Keep images updated** regularly for security patches

## Support

- Documentation: [docs/KUBERNETES_SETUP.md](../docs/KUBERNETES_SETUP.md)
- Issues: https://github.com/liberu-control-panel/control-panel-laravel/issues
