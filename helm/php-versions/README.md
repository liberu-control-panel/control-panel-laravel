# PHP Multi-Version Helm Chart

This Helm chart deploys multiple PHP-FPM versions (8.1-8.5) for the Liberu Control Panel, allowing different applications to use different PHP versions.

## Components

Deploys separate PHP-FPM deployments for:
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5

## Installation

```bash
helm install php-versions ./helm/php-versions \
  --namespace control-panel
```

## Configuration

### Enable/Disable Versions

```yaml
phpVersions:
  - version: "8.1"
    enabled: true
    replicaCount: 2
  - version: "8.2"
    enabled: true
    replicaCount: 2
  - version: "8.3"
    enabled: true
    replicaCount: 3  # More replicas for default version
  - version: "8.4"
    enabled: false   # Disable if not needed
  - version: "8.5"
    enabled: true
    replicaCount: 2
```

### PHP-FPM Configuration

```yaml
phpFpm:
  pmMaxChildren: 50
  pmStartServers: 10
  pmMinSpareServers: 5
  pmMaxSpareServers: 20
  pmMaxRequests: 500
  memoryLimit: 512M
  maxExecutionTime: 300
  uploadMaxFilesize: 100M
  postMaxSize: 100M
```

### PHP Extensions

Default extensions included:
- bcmath
- gd
- intl
- mbstring
- mysqli
- opcache
- pdo
- pdo_mysql
- redis
- xml
- zip
- imagick

### Opcache Configuration

```yaml
opcache:
  enabled: true
  memoryConsumption: 128
  maxAcceleratedFiles: 10000
  revalidateFreq: 2
```

## Services Exposed

Each PHP version is accessible via ClusterIP service:

```bash
# PHP 8.1
php-versions-8-1:9000

# PHP 8.2
php-versions-8-2:9000

# PHP 8.3
php-versions-8-3:9000

# PHP 8.4
php-versions-8-4:9000

# PHP 8.5
php-versions-8-5:9000
```

## Usage with NGINX

Configure NGINX to use specific PHP version per virtual host:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/example.com;

    location ~ \.php$ {
        fastcgi_pass php-versions-8-3:9000;  # Use PHP 8.3
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}

server {
    listen 80;
    server_name legacy.example.com;
    root /var/www/legacy.example.com;

    location ~ \.php$ {
        fastcgi_pass php-versions-8-1:9000;  # Use PHP 8.1 for legacy app
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Resource Management

Configure resources per version:

```yaml
resources:
  limits:
    cpu: 1000m
    memory: 1Gi
  requests:
    cpu: 200m
    memory: 256Mi
```

## Scaling

Scale individual PHP versions:

```bash
# Scale PHP 8.3 to 5 replicas
kubectl scale deployment php-versions-8-3 -n control-panel --replicas=5

# Scale PHP 8.1 to 1 replica (legacy support)
kubectl scale deployment php-versions-8-1 -n control-panel --replicas=1
```

## Monitoring

Each deployment includes:
- Liveness probes on port 9000
- Readiness probes on port 9000
- Resource limits and requests

Check PHP-FPM status:

```bash
# Get pods
kubectl get pods -n control-panel -l app=php-fpm

# Check logs for specific version
kubectl logs -n control-panel -l app=php-fpm,version=8.3
```

## Performance Tuning

### For High Traffic

```yaml
phpVersions:
  - version: "8.3"
    enabled: true
    replicaCount: 10  # More replicas

phpFpm:
  pmMaxChildren: 100
  pmStartServers: 20
  pmMinSpareServers: 10
  pmMaxSpareServers: 40

resources:
  limits:
    cpu: 2000m
    memory: 2Gi
```

### For Low Memory

```yaml
phpFpm:
  pmMaxChildren: 20
  pmStartServers: 5
  pmMinSpareServers: 2
  pmMaxSpareServers: 10
  memoryLimit: 256M

resources:
  limits:
    cpu: 500m
    memory: 512Mi
```

## Custom PHP Configuration

Add custom PHP.ini settings:

```yaml
# In values.yaml
phpCustomConfig: |
  date.timezone = America/New_York
  max_input_vars = 5000
  session.gc_maxlifetime = 7200
```

## Adding New Extensions

To add custom PHP extensions, create a custom Docker image:

```dockerfile
FROM php:8.3-fpm-alpine

# Install additional extensions
RUN apk add --no-cache \
    php83-mongodb \
    php83-xdebug

# Copy custom configuration
COPY custom.ini /usr/local/etc/php/conf.d/
```

Then update values:

```yaml
image:
  repository: your-registry/php-custom
  tag: 8.3-custom
```

## Uninstall

```bash
helm uninstall php-versions --namespace control-panel
```

## Troubleshooting

### Check PHP version

```bash
kubectl exec -n control-panel deployment/php-versions-8-3 -- php -v
```

### Test PHP-FPM

```bash
kubectl exec -n control-panel deployment/php-versions-8-3 -- php-fpm -t
```

### View PHP configuration

```bash
kubectl exec -n control-panel deployment/php-versions-8-3 -- php -i
```
