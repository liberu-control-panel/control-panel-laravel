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

## Example Installation with Custom Values

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

Install with:

```bash
helm install my-control-panel ./helm/control-panel -f values-production.yaml
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

## Support

For issues and questions:
- GitHub Issues: https://github.com/liberu-control-panel/control-panel-laravel/issues
- Documentation: https://github.com/liberu-control-panel/control-panel-laravel
