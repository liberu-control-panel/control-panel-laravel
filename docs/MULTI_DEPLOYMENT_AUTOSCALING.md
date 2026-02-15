# Multi-Deployment Support and Cloud Auto-Scaling

This control panel now supports multiple deployment methods and automatic scaling across various cloud providers, making it competitive with cPanel, Plesk, and DirectAdmin.

## Deployment Methods

The control panel automatically detects and supports three deployment methods:

### 1. Kubernetes (Recommended for Production)

**Features:**
- Full container orchestration
- Automatic horizontal and vertical scaling
- Load balancing
- Self-healing deployments
- Rolling updates with zero downtime

**Supported Platforms:**
- Self-managed Kubernetes clusters
- Amazon EKS (Elastic Kubernetes Service)
- Microsoft Azure AKS (Azure Kubernetes Service)
- Google GKE (Google Kubernetes Engine)
- DigitalOcean Kubernetes (DOKS)
- OVHcloud Managed Kubernetes

**Auto-Scaling Support:**
- ✅ Horizontal Pod Autoscaling (HPA) - scales number of pods based on CPU/memory
- ✅ Vertical Pod Autoscaling (VPA) - adjusts resource limits per pod
- ✅ Cluster Autoscaling - adds/removes nodes automatically

### 2. Docker Compose

**Features:**
- Container-based deployment
- Easy local development
- Multi-container applications
- Volume management
- Network isolation

**Best For:**
- Development environments
- Small to medium deployments
- Single-server setups

**Limitations:**
- No automatic scaling
- Manual load balancing required
- Limited high-availability options

### 3. Standalone (Traditional)

**Features:**
- Direct server deployment
- Traditional NGINX/Apache virtual hosts
- Direct process management
- Standard Linux services

**Best For:**
- Legacy systems
- Simple websites
- Environments without container support

**Limitations:**
- No automatic scaling
- Manual configuration required
- Limited automation

## Cloud Provider Detection

The system automatically detects the cloud provider using multiple methods:

1. **Kubernetes Node Labels** - Reads cloud-specific labels from K8s nodes
2. **Instance Metadata** - Queries cloud metadata services
3. **Environment Variables** - Checks for cloud-specific environment variables

**Supported Cloud Providers:**
- Amazon Web Services (AWS)
- Microsoft Azure
- Google Cloud Platform (GCP)
- DigitalOcean
- OVHcloud
- Linode
- Vultr
- Hetzner Cloud

## Auto-Scaling Configuration

### Global Settings

Navigate to **Admin Panel → System → Deployment** to view and configure:

- Current deployment mode (automatically detected)
- Cloud provider (automatically detected)
- Auto-scaling status
- System capabilities

### Per-Domain Scaling

For each domain/website, you can configure:

#### Horizontal Pod Autoscaling (HPA)

Automatically scales the number of pods based on resource usage:

- **Minimum Replicas**: Minimum number of pods (default: 1)
- **Maximum Replicas**: Maximum number of pods (default: 10)
- **Target CPU**: CPU utilization percentage to trigger scaling (default: 80%)

Example: If CPU usage exceeds 80%, new pods are automatically created until max replicas is reached.

#### Vertical Pod Autoscaling (VPA)

Automatically adjusts CPU and memory limits for pods:

- **Off**: Only provides recommendations, no automatic changes
- **Initial**: Applies recommendations when pods are created
- **Recreate**: Deletes and recreates pods with new limits
- **Auto**: Automatically updates pods (may cause brief downtime)

#### Manual Scaling

You can also manually set the exact number of replicas:

- Set replicas to `0` to stop the application
- Set replicas to `1+` to start/scale the application

### Using Auto-Scaling

1. Navigate to **App Panel → Domains**
2. Select a domain
3. Click **Actions → Manage Scaling**
4. Configure your scaling preferences
5. Click **Save**

The system will automatically:
- Create HorizontalPodAutoscaler resources
- Create VerticalPodAutoscaler resources (if enabled)
- Monitor and scale your application

## Deployment Methods Comparison

| Feature | Kubernetes | Docker Compose | Standalone |
|---------|-----------|----------------|------------|
| Auto-Scaling | ✅ HPA + VPA | ❌ | ❌ |
| Load Balancing | ✅ Built-in | ⚠️ Manual | ⚠️ Manual |
| High Availability | ✅ Multi-node | ⚠️ Limited | ❌ |
| Zero-Downtime Deploys | ✅ Rolling | ⚠️ With config | ❌ |
| Resource Isolation | ✅ Strong | ✅ Good | ⚠️ Limited |
| SSL Automation | ✅ cert-manager | ✅ Let's Encrypt | ✅ Certbot |
| Complexity | High | Medium | Low |
| Resource Overhead | Medium | Low | Minimal |

## API Reference

### Deployment Detection Service

```php
use App\Services\DeploymentDetectionService;

$service = app(DeploymentDetectionService::class);

// Get deployment mode
$mode = $service->detectDeploymentMode();
// Returns: 'standalone', 'docker-compose', or 'kubernetes'

// Get cloud provider
$provider = $service->detectCloudProvider();
// Returns: 'aws', 'azure', 'gcp', 'digitalocean', etc.

// Get full deployment info
$info = $service->getDeploymentInfo();
// Returns array with mode, provider, capabilities, etc.

// Check specific environment
$isK8s = $service->isKubernetes();
$isDocker = $service->isDocker();
$isStandalone = $service->isStandalone();

// Check auto-scaling support
$canScale = $service->supportsAutoScaling();
```

### Cloud Provider Manager

```php
use App\Services\CloudProviderManager;

$manager = app(CloudProviderManager::class);

// Get provider for a server
$provider = $manager->getProvider($server);

// Enable horizontal scaling
$provider->enableHorizontalScaling(
    domain: $domain,
    minReplicas: 2,
    maxReplicas: 10,
    targetCpuUtilization: 75
);

// Enable vertical scaling
$provider->enableVerticalScaling(
    domain: $domain,
    options: ['update_mode' => 'Auto']
);

// Get current scaling config
$config = $provider->getScalingConfig($domain);

// Manual scaling
$provider->scaleToReplicas($domain, 5);

// Get resource metrics
$metrics = $provider->getResourceMetrics($domain);
```

### Deployment-Aware Service

```php
use App\Services\DeploymentAwareService;

$service = app(DeploymentAwareService::class);

// Deploy domain (automatically uses correct method)
$service->deployDomain($domain, $options);

// Delete domain
$service->deleteDomain($domain);

// Get deployment status
$status = $service->getDeploymentStatus($domain);

// Restart deployment
$service->restartDomain($domain);
```

## Migration from Other Panels

### From cPanel

The control panel provides similar functionality to cPanel:

| cPanel Feature | Control Panel Equivalent |
|----------------|-------------------------|
| WHM | Admin Panel |
| User Panel | App Panel |
| AutoSSL | cert-manager / Let's Encrypt |
| Resource Limits | Kubernetes Resource Quotas |
| Email Accounts | Mail Services (Postfix/Dovecot) |
| FTP Accounts | SFTP/FTP Services |
| File Manager | File Manager Service |
| Databases | MySQL/PostgreSQL Services |

### From Plesk

Similar to cPanel, Plesk features map to:

| Plesk Feature | Control Panel Equivalent |
|---------------|-------------------------|
| Service Plans | Hosting Plans |
| Docker Support | Native Docker/K8s |
| Git Deployment | Git Deployment Service |
| WordPress Toolkit | WordPress Auto-Deployment |
| Security | NetworkPolicies + RBAC |

### From DirectAdmin

DirectAdmin users will find:

| DirectAdmin Feature | Control Panel Equivalent |
|--------------------|-------------------------|
| User Levels | Team-based RBAC |
| Reseller Accounts | Multi-tenancy Support |
| Backup/Restore | Backup Services |
| Custom NS | DNS Cluster |

## Best Practices

### Production Deployments

1. **Use Kubernetes** for production workloads
2. **Enable Auto-Scaling** for traffic spikes
3. **Set Resource Limits** to prevent resource exhaustion
4. **Use VPA with "Initial"** mode to avoid disruption
5. **Monitor Metrics** regularly

### Development Environments

1. **Use Docker Compose** for local development
2. **Match production config** as closely as possible
3. **Use volume mounts** for faster iterations

### Resource Limits

#### Small Sites (< 1000 visitors/day)
- Min Replicas: 1
- Max Replicas: 3
- CPU Target: 80%
- Memory: 256Mi - 512Mi

#### Medium Sites (1000-10000 visitors/day)
- Min Replicas: 2
- Max Replicas: 5
- CPU Target: 70%
- Memory: 512Mi - 1Gi

#### Large Sites (> 10000 visitors/day)
- Min Replicas: 3
- Max Replicas: 10+
- CPU Target: 60%
- Memory: 1Gi - 2Gi

## Troubleshooting

### Auto-Scaling Not Working

1. Check deployment mode: `Admin Panel → Deployment`
2. Verify Kubernetes is detected
3. Verify cloud provider is detected
4. Check metrics-server is installed: `kubectl get deployment metrics-server -n kube-system`

### Pods Not Scaling

1. Check HPA status: Navigate to domain → Manage Scaling
2. Verify resource requests are set
3. Check pod metrics: View resource metrics in scaling dialog
4. Ensure CPU/memory thresholds are reached

### Deployment Fails

1. Check server type matches deployment method
2. Verify server credentials
3. Check logs in deployment status
4. Ensure required services are running

## Support

For issues or questions:

1. Check GitHub Issues: https://github.com/liberu-control-panel/control-panel-laravel/issues
2. Read documentation: https://liberu.co.uk
3. Community support via GitHub Discussions

## License

This feature is part of the Liberu Control Panel and is licensed under the MIT License.
