# Liberu Control Panel Helm Chart

This Helm chart deploys the Liberu Control Panel on a Kubernetes cluster.

## Prerequisites

- Kubernetes 1.20+
- Helm 3.0+
- PV provisioner support in the underlying infrastructure
- NGINX Ingress Controller
- cert-manager (optional, for automatic TLS certificates)

## Installing the Chart

To install the chart with the release name `my-control-panel`:

```bash
helm install my-control-panel ./helm/control-panel
```

Or install from a packaged chart:

```bash
helm package ./helm/control-panel
helm install my-control-panel control-panel-1.0.0.tgz
```

## Uninstalling the Chart

To uninstall/delete the `my-control-panel` deployment:

```bash
helm uninstall my-control-panel
```

## Configuration

The following table lists the configurable parameters of the Control Panel chart and their default values.

### Application Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `replicaCount` | Number of replicas | `2` |
| `image.repository` | Image repository | `ghcr.io/liberu-control-panel/control-panel-laravel` |
| `image.tag` | Image tag | `latest` |
| `image.pullPolicy` | Image pull policy | `IfNotPresent` |
| `app.env` | Application environment | `production` |
| `app.debug` | Enable debug mode | `false` |
| `app.key` | Application key (required) | `""` |
| `app.url` | Application URL | `https://control-panel.example.com` |

### Database Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `mysql.enabled` | Enable MySQL | `true` |
| `mysql.auth.database` | Database name | `controlpanel` |
| `mysql.auth.username` | Database username | `controlpanel` |
| `mysql.auth.password` | Database password (required) | `""` |
| `mysql.auth.rootPassword` | Database root password (required) | `""` |
| `mysql.primary.persistence.size` | Database storage size | `20Gi` |

### Redis Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `redis.enabled` | Enable Redis | `true` |
| `redis.auth.enabled` | Enable Redis authentication | `false` |

### Ingress Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `ingress.enabled` | Enable ingress | `true` |
| `ingress.className` | Ingress class name | `nginx` |
| `ingress.hosts[0].host` | Hostname | `control-panel.example.com` |
| `ingress.tls[0].secretName` | TLS secret name | `control-panel-tls` |

### Autoscaling Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `autoscaling.enabled` | Enable HPA | `true` |
| `autoscaling.minReplicas` | Minimum replicas | `2` |
| `autoscaling.maxReplicas` | Maximum replicas | `10` |
| `autoscaling.targetCPUUtilizationPercentage` | Target CPU % | `70` |
| `autoscaling.targetMemoryUtilizationPercentage` | Target Memory % | `80` |

### Resource Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `resources.limits.cpu` | CPU limit | `1000m` |
| `resources.limits.memory` | Memory limit | `1Gi` |
| `resources.requests.cpu` | CPU request | `200m` |
| `resources.requests.memory` | Memory request | `256Mi` |

### Storage Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `persistence.enabled` | Enable persistent storage | `true` |
| `persistence.storageClass` | Storage class name | `standard` |
| `persistence.size` | Storage size | `10Gi` |
| `persistence.accessMode` | Access mode | `ReadWriteOnce` |

### S3 Storage Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `s3.enabled` | Enable S3-compatible storage | `false` |
| `s3.endpoint` | S3 endpoint URL | `""` |
| `s3.accessKey` | S3 access key | `""` |
| `s3.secretKey` | S3 secret key | `""` |
| `s3.bucket` | S3 bucket name | `""` |
| `s3.region` | S3 region | `us-east-1` |
| `s3.usePathStyle` | Use path-style URLs (for MinIO) | `true` |
| `s3.storageClassName` | Storage class name for S3 | `s3-storage` |

## Example Installation with Custom Values

### Basic Production Configuration

Create a `values-production.yaml` file:

```yaml
app:
  env: production
  debug: false
  key: "base64:YOUR_GENERATED_APP_KEY"
  url: https://control.yourdomain.com

mysql:
  auth:
    password: "your-secure-database-password"
    rootPassword: "your-secure-root-password"

ingress:
  hosts:
    - host: control.yourdomain.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: control-panel-tls
      hosts:
        - control.yourdomain.com

resources:
  limits:
    cpu: 2000m
    memory: 2Gi
  requests:
    cpu: 500m
    memory: 512Mi
```

### Production Configuration with S3 Storage

Create a `values-production-s3.yaml` file:

```yaml
app:
  env: production
  debug: false
  key: "base64:YOUR_GENERATED_APP_KEY"
  url: https://control.yourdomain.com

mysql:
  auth:
    password: "your-secure-database-password"
    rootPassword: "your-secure-root-password"
  primary:
    persistence:
      storageClass: "s3-storage"  # Use S3 storage for database
  secondary:
    persistence:
      storageClass: "s3-storage"  # Use S3 storage for replicas

ingress:
  hosts:
    - host: control.yourdomain.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: control-panel-tls
      hosts:
        - control.yourdomain.com

# S3-compatible storage configuration
s3:
  enabled: true
  endpoint: "https://s3.amazonaws.com"
  accessKey: "your-s3-access-key"
  secretKey: "your-s3-secret-key"
  bucket: "control-panel-storage"
  region: "us-east-1"
  usePathStyle: false

resources:
  limits:
    cpu: 2000m
    memory: 2Gi
  requests:
    cpu: 500m
    memory: 512Mi
```

### MinIO Storage Configuration

For self-hosted MinIO object storage:

```yaml
app:
  env: production
  debug: false
  key: "base64:YOUR_GENERATED_APP_KEY"
  url: https://control.yourdomain.com

s3:
  enabled: true
  endpoint: "https://minio.yourdomain.com"
  accessKey: "minioadmin"
  secretKey: "minioadmin-password"
  bucket: "control-panel"
  region: "us-east-1"
  usePathStyle: true  # MinIO requires path-style URLs

ingress:
  hosts:
    - host: control.yourdomain.com
```

### DigitalOcean Spaces Configuration

For DigitalOcean Spaces:

```yaml
s3:
  enabled: true
  endpoint: "https://nyc3.digitaloceanspaces.com"
  accessKey: "DO00EXAMPLE"
  secretKey: "your-spaces-secret-key"
  bucket: "my-space-name"
  region: "nyc3"
  usePathStyle: false
```

Install with:

```bash
helm install my-control-panel ./helm/control-panel -f values-production.yaml
```

Or with S3 storage:

```bash
helm install my-control-panel ./helm/control-panel -f values-production-s3.yaml
```

### Command-Line S3 Configuration

You can also configure S3 directly via command-line flags:

```bash
helm install my-control-panel ./helm/control-panel \
  --set app.key="base64:YOUR_APP_KEY" \
  --set app.url="https://control.yourdomain.com" \
  --set s3.enabled=true \
  --set s3.endpoint="https://s3.amazonaws.com" \
  --set s3.accessKey="your-access-key" \
  --set s3.secretKey="your-secret-key" \
  --set s3.bucket="control-panel-storage" \
  --set s3.region="us-east-1"
```

## Post-Installation

1. **Generate Application Key** (if not set in values):
   ```bash
   kubectl exec -it deployment/my-control-panel -- php artisan key:generate --show
   ```

2. **Run Migrations**:
   ```bash
   kubectl exec -it deployment/my-control-panel -- php artisan migrate --force
   ```

3. **Seed Database**:
   ```bash
   kubectl exec -it deployment/my-control-panel -- php artisan db:seed
   ```

4. **Create Admin User**:
   ```bash
   kubectl exec -it deployment/my-control-panel -- php artisan make:filament-user
   ```

## Upgrading

To upgrade the release:

```bash
helm upgrade my-control-panel ./helm/control-panel -f values-production.yaml
```

## Troubleshooting

### Check Pod Status
```bash
kubectl get pods -l app.kubernetes.io/name=control-panel
```

### View Logs
```bash
kubectl logs -l app.kubernetes.io/name=control-panel -c php-fpm
kubectl logs -l app.kubernetes.io/name=control-panel -c nginx
```

### Access Pod Shell
```bash
kubectl exec -it deployment/my-control-panel -c php-fpm -- /bin/sh
```

### Check Database Connection
```bash
kubectl exec -it deployment/my-control-panel -c php-fpm -- php artisan tinker --execute="DB::connection()->getPdo();"
```

### Test S3 Storage Connection

If you've enabled S3 storage, test the connection:

```bash
# Check S3 credentials in secret
kubectl get secret my-control-panel-secrets -o jsonpath='{.data.AWS_ACCESS_KEY_ID}' | base64 -d

# Test S3 connection from pod
kubectl exec -it deployment/my-control-panel -c php-fpm -- php artisan tinker

# In tinker, run:
# Storage::disk('s3')->put('test.txt', 'Hello from Kubernetes');
# Storage::disk('s3')->exists('test.txt');
# Storage::disk('s3')->get('test.txt');
```

### Common S3 Issues

**Connection Timeout:**
```bash
# Verify endpoint is reachable
kubectl run -it --rm debug --image=curlimages/curl --restart=Never -- curl -v YOUR_S3_ENDPOINT
```

**Access Denied:**
- Verify S3 credentials are correct
- Check bucket permissions/policies
- Ensure IAM user has required S3 permissions

**Path Style Endpoint:**
- For MinIO and some S3 services, set `s3.usePathStyle: true`

## S3 Storage Configuration

For detailed S3 storage configuration, including:
- Supported S3-compatible services (AWS S3, MinIO, DigitalOcean Spaces, etc.)
- Complete setup instructions
- Migration from local to S3 storage
- Performance optimization
- Security best practices

See the comprehensive [S3 Storage Guide](../../docs/S3_STORAGE.md).

## Support

For issues and questions:
- GitHub Issues: https://github.com/liberu-control-panel/control-panel-laravel/issues
- Documentation: https://github.com/liberu-control-panel/control-panel-laravel
