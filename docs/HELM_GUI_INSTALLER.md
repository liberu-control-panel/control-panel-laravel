# GUI Helm Chart Installer

The Control Panel includes a graphical interface for installing and managing Helm charts on your Kubernetes clusters.

## Features

- **One-Click Installation**: Deploy services with a few clicks
- **Pre-configured Charts**: Optimized configurations for common services
- **Visual Management**: Track installation status, versions, and health
- **Automatic Updates**: Upgrade charts directly from the GUI
- **NGINX Ingress Integration**: Automatic virtual host configuration
- **Multi-Server Support**: Manage charts across multiple Kubernetes clusters

## Available Charts

### Database
- **MariaDB**: High-availability database cluster with replication
- **PostgreSQL**: Advanced relational database
- **MongoDB**: NoSQL document database

### Caching & Queue
- **Redis**: In-memory data store for caching and sessions
- **RabbitMQ**: Message broker for queuing

### Mail Services
- **Postfix**: SMTP server for sending emails
- **Dovecot**: IMAP/POP3 server for receiving emails

### DNS
- **PowerDNS**: Authoritative DNS server cluster
- **PowerDNS Admin**: Web interface for DNS management

### PHP
- **PHP Multi-Version**: PHP-FPM versions 8.1 through 8.5

### Search
- **Elasticsearch**: Search and analytics engine

## Using the GUI Installer

### 1. Access the Helm Charts Manager

Navigate to **Kubernetes** → **Helm Charts** in the control panel.

### 2. Install a New Chart

1. Click **"Install Chart"** button
2. Select the target **Server** (Kubernetes cluster)
3. Enter a unique **Release Name** (e.g., `production-db`)
4. Choose the **Chart** to install
5. Specify the **Namespace** (default: `default`)
6. Customize **Helm Values** as needed
7. Add optional **Notes** for documentation
8. Click **"Create"** to begin installation

### 3. Monitor Installation

The chart will be installed asynchronously. Monitor the status:
- **Pending**: Installation in progress
- **Deployed**: Successfully installed and running
- **Failed**: Installation encountered an error

### 4. Manage Installed Charts

From the Helm Charts list, you can:
- **Sync Status**: Refresh status from the cluster
- **Edit**: Modify configuration values
- **Upgrade**: Apply updated configuration
- **Uninstall**: Remove the chart

## Configuration Examples

### MariaDB Database

```yaml
# Helm Values
auth.database: myapp
auth.username: myapp_user
auth.password: secure-password
architecture: replication
secondary.replicaCount: 2
metrics.enabled: true
```

**Access:**
```
Host: <release-name>.<namespace>.svc.cluster.local
Port: 3306
```

### Redis Cache

```yaml
# Helm Values
auth.enabled: false
replica.replicaCount: 2
metrics.enabled: true
```

**Access:**
```
Host: <release-name>-master.<namespace>.svc.cluster.local
Port: 6379
```

### Mail Services

```yaml
# Helm Values
postfix.config.domain: yourdomain.com
postfix.config.hostname: mail.yourdomain.com
dovecot.config.hostname: mail.yourdomain.com
dovecot.persistence.size: 20Gi
```

**Services:**
- SMTP: Port 587
- IMAP: Port 143 (993 with TLS)
- POP3: Port 110 (995 with TLS)

### DNS Cluster

```yaml
# Helm Values
powerdns.mysql.password: secure-db-password
powerdns.api.key: secure-api-key
powerdns.replicaCount: 3
```

**Services:**
- DNS: Port 53 (TCP/UDP)
- API: Port 8081 (internal)

### PHP Multi-Version

```yaml
# Helm Values
phpVersions[0].version: "8.1"
phpVersions[0].enabled: true
phpVersions[0].replicaCount: 2

phpVersions[2].version: "8.3"
phpVersions[2].enabled: true
phpVersions[2].replicaCount: 3
```

**Services:**
- PHP 8.1: `php-versions-8-1:9000`
- PHP 8.2: `php-versions-8-2:9000`
- PHP 8.3: `php-versions-8-3:9000`

## Virtual Host Integration

When installing services, the control panel can automatically configure NGINX Ingress virtual hosts:

### Example: Web Application with Database

1. **Install MariaDB:**
   - Release: `myapp-db`
   - Namespace: `myapp`

2. **Install PHP 8.3:**
   - Release: `php-83`
   - Namespace: `myapp`

3. **Create Virtual Host:**
   - Domain: `myapp.com`
   - PHP Version: `8.3`
   - Database: `myapp-db.myapp.svc.cluster.local:3306`

The control panel will:
- Create NGINX Ingress configuration
- Request Let's Encrypt SSL certificate
- Configure PHP-FPM backend
- Set up database connection

## Automation Features

### Auto-Scaling

Charts with HPA (Horizontal Pod Autoscaler) support will automatically scale based on:
- CPU utilization
- Memory usage
- Custom metrics

### Health Monitoring

The control panel monitors:
- Pod status and health
- Resource usage
- Deployment events
- Error logs

### Backup Integration

Databases can be automatically backed up:
- Scheduled snapshots
- Point-in-time recovery
- Cross-cluster replication

## Advanced Configuration

### Custom Values Files

For complex configurations, upload a `values.yaml` file:

```yaml
# values.yaml
mariadb:
  architecture: replication
  auth:
    rootPassword: ${DB_ROOT_PASSWORD}
    database: myapp
    username: myapp_user
    password: ${DB_PASSWORD}
  primary:
    persistence:
      size: 50Gi
      storageClass: fast-ssd
  secondary:
    replicaCount: 3
    persistence:
      size: 50Gi
  metrics:
    enabled: true
    serviceMonitor:
      enabled: true
```

### Environment Variables

Use environment variables in values:
- `${DB_PASSWORD}`: Database password from secrets
- `${DOMAIN}`: Primary domain
- `${NAMESPACE}`: Target namespace

### Dependencies

Some charts depend on others. Install in order:
1. **Infrastructure**: MariaDB, Redis, DNS
2. **Services**: Mail, PHP
3. **Applications**: Control Panel, custom apps

## Troubleshooting

### Installation Failed

1. Check the **Notes** field for error messages
2. Click **Sync Status** to refresh
3. View pod logs in Kubernetes
4. Verify resources are available

### Chart Won't Upgrade

1. Check for breaking changes in chart version
2. Review current values vs. new values
3. Test in staging environment first
4. Use `helm diff` plugin to preview changes

### Service Not Accessible

1. Verify ingress configuration
2. Check DNS records
3. Review NetworkPolicies
4. Test connectivity from pod

## Best Practices

### Naming Conventions

- Use descriptive release names: `prod-mariadb`, `staging-redis`
- Include environment in name when managing multiple clusters
- Keep names short but meaningful

### Namespace Organization

- Separate by environment: `production`, `staging`, `development`
- Or by application: `myapp`, `api-service`, `frontend`
- Use labels for additional metadata

### Security

- Store sensitive values in Kubernetes Secrets
- Use RBAC to limit access
- Enable audit logging
- Regular security updates

### Resource Management

- Set appropriate resource limits
- Use resource quotas per namespace
- Monitor resource usage
- Plan for peak loads

## API Integration

The Helm chart installer can be automated via API:

```bash
# Install a chart via API
curl -X POST https://control.example.com/api/helm/install \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "server_id": 1,
    "chart_name": "mariadb",
    "release_name": "prod-db",
    "namespace": "production",
    "values": {
      "auth.database": "myapp",
      "auth.password": "secure-password"
    }
  }'
```

See [API Documentation](../API.md) for complete reference.

## Support

- **Documentation**: [Kubernetes Installation Guide](KUBERNETES_INSTALLATION.md)
- **Issues**: [GitHub Issues](https://github.com/liberu-control-panel/control-panel-laravel/issues)
- **Community**: [GitHub Discussions](https://github.com/liberu-control-panel/control-panel-laravel/discussions)
