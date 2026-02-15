# Security Implementation Guide

This document outlines the security improvements implemented in the Liberu Control Panel, focusing on secrets management, non-root user configuration, and Kubernetes node management.

## Table of Contents

1. [Docker Security Improvements](#docker-security-improvements)
2. [Kubernetes Security](#kubernetes-security)
3. [Secrets Management](#secrets-management)
4. [Kubernetes Node Management](#kubernetes-node-management)
5. [Best Practices](#best-practices)

## Docker Security Improvements

### Non-Root User

The application now runs as a non-root user (`appuser:1000`) instead of root, following security best practices.

**Changes:**
- Created dedicated application user with UID/GID 1000
- PHP-FPM configured to run as `appuser`
- All application files owned by `appuser`
- Proper permissions set for Laravel storage and cache directories

**Configuration:**
```dockerfile
# Create application user (non-root)
RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -g appuser -m -s /bin/bash appuser

# Switch to non-root user
USER appuser
```

### Enhanced Entrypoint Script

A new entrypoint script (`.docker/entrypoint.sh`) handles:
- Loading secrets from files (`/run/secrets/*`)
- Supporting `_FILE` suffix environment variables
- Waiting for database availability
- Running migrations (if enabled)
- Caching configuration for production

**Environment Variables:**
- `WAIT_FOR_DB` - Wait for database (default: true)
- `RUN_MIGRATIONS` - Run migrations on startup (default: false)
- `SKIP_INIT` - Skip Laravel initialization (default: false)

## Kubernetes Security

### Pod Security Context

All Kubernetes deployments enforce security contexts:

```yaml
podSecurityContext:
  fsGroup: 1000
  runAsNonRoot: true
  runAsUser: 1000

securityContext:
  allowPrivilegeEscalation: false
  capabilities:
    drop:
    - ALL
```

### Network Policies

Network policies are automatically applied to isolate namespaces and control traffic flow (configured in `KubernetesSecurityService`).

### RBAC

Role-Based Access Control (RBAC) policies limit permissions for service accounts within each namespace.

## Secrets Management

### Docker Compose Secrets

Secrets are stored in files and mounted into containers:

**Setup:**
```bash
# Create secret files
echo "your-db-password" > secrets/db_password.txt
echo "your-root-password" > secrets/db_root_password.txt
echo "base64:your-app-key" > secrets/app_key.txt

# Set proper permissions
chmod 600 secrets/*.txt
```

**docker-compose.yml:**
```yaml
services:
  control-panel:
    secrets:
      - db_password
    environment:
      - DB_PASSWORD_FILE=/run/secrets/db_password

secrets:
  db_password:
    file: ./secrets/db_password.txt
```

### Kubernetes Secrets

**Create secrets:**
```bash
kubectl create secret generic control-panel-secrets \
  --from-literal=app_key='base64:your-generated-key' \
  --from-literal=db_password='your-db-password' \
  --from-literal=db_root_password='your-root-password' \
  -n control-panel
```

**Or using Helm:**
```bash
helm install control-panel ./helm/control-panel \
  --set app.key="base64:your-generated-key" \
  --set mysql.auth.password="your-db-password" \
  --set mysql.auth.rootPassword="your-root-password"
```

### Supported Secret Locations

The entrypoint script automatically loads secrets from:

1. **File-based secrets** (`/run/secrets/*`):
   - `db_password`
   - `db_root_password`
   - `app_key`
   - `redis_password`
   - `aws_access_key_id`
   - `aws_secret_access_key`

2. **Environment variables with `_FILE` suffix**:
   - `DB_PASSWORD_FILE=/path/to/secret`
   - `APP_KEY_FILE=/path/to/secret`

3. **Direct environment variables** (less secure, for development only)

### External Secret Management (Recommended for Production)

For production environments, integrate with enterprise secret management:

**Sealed Secrets:**
```bash
# Install Sealed Secrets controller
kubectl apply -f https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.18.0/controller.yaml

# Create sealed secret
kubeseal --format=yaml < secret.yaml > sealed-secret.yaml
kubectl apply -f sealed-secret.yaml
```

**External Secrets Operator:**
```yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: control-panel-secrets
spec:
  secretStoreRef:
    name: aws-secrets-manager
  target:
    name: control-panel-secrets
  data:
  - secretKey: db_password
    remoteRef:
      key: prod/control-panel/db-password
```

## Kubernetes Node Management

### Overview

The Kubernetes Node Management feature provides a web-based interface for managing cluster nodes.

### Features

1. **Node Discovery & Synchronization**
   - Automatic discovery of cluster nodes
   - Real-time status synchronization
   - Resource capacity and allocatable tracking

2. **Node Operations**
   - **Cordon**: Mark node as unschedulable
   - **Uncordon**: Mark node as schedulable
   - **Drain**: Safely evict all pods from node
   - **Label**: Add/remove custom labels
   - **View Details**: CPU, memory, system info

3. **Monitoring**
   - Node health status (Ready, NotReady, Unknown)
   - Resource utilization (CPU, Memory)
   - Kubernetes version tracking
   - Last heartbeat time

### Usage

**Access the UI:**
Navigate to: Admin Panel → Infrastructure → Kubernetes Nodes

**Sync Nodes:**
Click the "Sync" button to refresh node data from all Kubernetes servers.

**Manage Nodes:**
- **Cordon**: Prevents new pods from being scheduled on the node
- **Uncordon**: Allows pods to be scheduled on the node again
- **Drain**: Evicts all pods (useful before maintenance)

**Programmatic Access:**
```php
use App\Services\KubernetesNodeService;
use App\Models\Server;

$service = app(KubernetesNodeService::class);
$server = Server::kubernetes()->first();

// Sync nodes from cluster
$service->syncNodes($server);

// Get all nodes
$nodes = $server->kubernetesNodes;

// Cordon a node
$node = KubernetesNode::where('name', 'worker-1')->first();
$service->cordonNode($node);

// Drain a node
$service->drainNode($node, [
    'force' => false,
    'grace_period' => 30,
    'timeout' => '5m',
]);
```

### Database Schema

The `kubernetes_nodes` table stores:
- Node identification (name, UID)
- Status and schedulability
- System information (OS, kernel, K8s version)
- Resources (capacity, allocatable)
- Labels, annotations, and taints
- Network addresses
- Conditions and health status

## Best Practices

### Development

1. **Use Docker Compose** with file-based secrets
2. **Keep secrets in `.gitignore`** to prevent accidental commits
3. **Use `.env.example`** for non-sensitive configuration templates
4. **Test with non-root user** to catch permission issues early

### Staging

1. **Use Kubernetes secrets** instead of environment variables
2. **Enable pod security policies**
3. **Test RBAC and network policies**
4. **Regular security scans** of container images

### Production

1. **External Secret Management** (Vault, AWS Secrets Manager, etc.)
2. **Sealed Secrets** or External Secrets Operator
3. **Regular secret rotation** (automated)
4. **Audit logging** for secret access
5. **Principle of least privilege** for RBAC
6. **Network policies** for namespace isolation
7. **Pod Security Standards** (restricted mode)
8. **Image vulnerability scanning** in CI/CD
9. **Runtime security monitoring**

### Secret Rotation

**Kubernetes:**
```bash
# Update secret
kubectl create secret generic control-panel-secrets \
  --from-literal=db_password='new-password' \
  --dry-run=client -o yaml | kubectl apply -f -

# Restart pods to use new secret
kubectl rollout restart deployment/control-panel -n control-panel
```

**Docker Compose:**
```bash
# Update secret file
echo "new-password" > secrets/db_password.txt

# Recreate containers
docker-compose up -d --force-recreate control-panel
```

## Troubleshooting

### Permission Denied Errors

If you encounter permission errors with the non-root user:

```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
chown -R 1000:1000 storage bootstrap/cache
```

### Secrets Not Loading

Check the entrypoint logs:
```bash
# Docker
docker logs control-panel

# Kubernetes
kubectl logs deployment/control-panel -n control-panel
```

Verify secret files exist and are readable:
```bash
# Docker
docker exec control-panel ls -la /run/secrets/

# Kubernetes
kubectl exec deployment/control-panel -n control-panel -- ls -la /run/secrets/
```

### Node Sync Issues

If nodes don't appear in the UI:

1. Check server configuration (must be type: kubernetes)
2. Verify kubectl is accessible on the server
3. Check SSH connection to Kubernetes master
4. Review logs: `storage/logs/laravel.log`

## Security Checklist

- [ ] All containers run as non-root user
- [ ] Secrets stored in secure storage (not in code)
- [ ] Pod security contexts configured
- [ ] Network policies enabled
- [ ] RBAC policies in place
- [ ] Resource limits set for all pods
- [ ] Image vulnerability scanning enabled
- [ ] Secret rotation policy defined
- [ ] Audit logging enabled
- [ ] Regular security updates scheduled
- [ ] Backup and disaster recovery tested

## References

- [Kubernetes Security Best Practices](https://kubernetes.io/docs/concepts/security/pod-security-standards/)
- [Docker Security](https://docs.docker.com/engine/security/)
- [OWASP Container Security](https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html)
- [CIS Kubernetes Benchmark](https://www.cisecurity.org/benchmark/kubernetes)
