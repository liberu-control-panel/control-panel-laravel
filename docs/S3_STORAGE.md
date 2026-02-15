# S3-Compatible Storage for Kubernetes Persistent Volumes

This guide explains how to configure S3-compatible storage (AWS S3, MinIO, DigitalOcean Spaces, etc.) for persistent volumes in your Kubernetes deployment of the Liberu Control Panel.

## Table of Contents

- [Overview](#overview)
- [Why Use S3 Storage?](#why-use-s3-storage)
- [Supported S3 Services](#supported-s3-services)
- [Prerequisites](#prerequisites)
- [Installation Options](#installation-options)
  - [Option 1: Automated Installation](#option-1-automated-installation)
  - [Option 2: Manual Configuration](#option-2-manual-configuration)
- [Configuration Examples](#configuration-examples)
- [Using S3 with Helm](#using-s3-with-helm)
- [Troubleshooting](#troubleshooting)
- [Best Practices](#best-practices)

## Overview

The Liberu Control Panel supports S3-compatible object storage for persistent volumes in Kubernetes deployments. This allows for better scalability, durability, and cross-node data access compared to traditional local or network-attached storage.

## Why Use S3 Storage?

**Benefits:**
- **Scalability**: Automatically scales with your data without capacity planning
- **Durability**: Object storage typically offers 99.999999999% (11 nines) durability
- **Availability**: Data accessible from any Kubernetes node in the cluster
- **Cost-Effective**: Pay only for what you use with most cloud providers
- **Multi-Region**: Supports geographic distribution for disaster recovery
- **Backup Integration**: Easy integration with backup and archival systems
- **Performance**: Optimized for distributed applications

**Use Cases:**
- User-uploaded files and media
- Application logs and backups
- Static assets and public files
- Mail storage (when using mail services)
- DNS zone files (when using DNS cluster)
- **Database persistent volumes (MariaDB, PostgreSQL)**
- Database backups

## Supported S3 Services

The control panel supports any S3-compatible storage service:

### Cloud Providers
- **AWS S3** - Amazon Web Services object storage
- **DigitalOcean Spaces** - S3-compatible object storage
- **Linode Object Storage** - S3-compatible storage
- **Wasabi** - Hot cloud storage
- **Backblaze B2** - Cloud storage with S3 API
- **Cloudflare R2** - Zero-egress object storage

### Self-Hosted
- **MinIO** - High-performance, Kubernetes-native object storage
- **Ceph** - Distributed storage with S3 gateway
- **SeaweedFS** - Distributed object storage

## Prerequisites

Before configuring S3 storage, ensure you have:

1. **S3 Bucket**: Create a bucket in your S3 service
2. **Access Credentials**: Obtain access key ID and secret access key
3. **Endpoint URL**: Get the endpoint URL for your S3 service
4. **Region**: Know the region where your bucket is located
5. **Kubernetes Cluster**: Running Kubernetes cluster (installed via `install-k8s.sh`)
6. **S3 CSI Driver** (for database storage): For using S3 with databases like MariaDB, you'll need:
   - A CSI driver that supports block storage over S3 (e.g., MinIO DirectPV, s3fs-fuse)
   - Or a storage solution that provides S3-compatible block volumes
   - Note: Standard S3 object storage works well for application files, but databases may require block storage emulation

## Installation Options

### Option 1: Automated Installation

The easiest way to configure S3 storage is during the initial installation using the `install-control-panel.sh` script:

```bash
# Run the installation script
./install-control-panel.sh

# When prompted, choose to configure S3 storage
# The script will ask for:
# - S3 endpoint URL
# - Access key
# - Secret key
# - Bucket name
# - Region
```

The script will automatically:
- Configure the Helm chart with S3 credentials
- Set up environment variables
- Create Kubernetes secrets
- Configure storage classes

### Option 2: Manual Configuration

For manual configuration or when updating an existing installation:

#### Step 1: Set Environment Variables

```bash
export S3_ENDPOINT="https://s3.amazonaws.com"
export S3_ACCESS_KEY="your-access-key"
export S3_SECRET_KEY="your-secret-key"
export S3_BUCKET="control-panel-storage"
export S3_REGION="us-east-1"
```

#### Step 2: Install/Upgrade with Helm

```bash
helm upgrade --install control-panel ./helm/control-panel \
  --namespace control-panel \
  --set s3.enabled=true \
  --set s3.endpoint="$S3_ENDPOINT" \
  --set s3.accessKey="$S3_ACCESS_KEY" \
  --set s3.secretKey="$S3_SECRET_KEY" \
  --set s3.bucket="$S3_BUCKET" \
  --set s3.region="$S3_REGION" \
  --set persistence.storageClass="s3-storage"
```

#### Step 3: Verify Configuration

```bash
# Check if secrets were created
kubectl get secrets -n control-panel

# Verify pods are using S3 configuration
kubectl describe pod -n control-panel -l app.kubernetes.io/name=control-panel
```

## Configuration Examples

### AWS S3

```yaml
s3:
  enabled: true
  endpoint: "https://s3.amazonaws.com"
  accessKey: "AKIAIOSFODNN7EXAMPLE"
  secretKey: "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
  bucket: "my-control-panel-bucket"
  region: "us-east-1"
  usePathStyle: false
```

### MinIO (Self-Hosted)

```yaml
s3:
  enabled: true
  endpoint: "https://minio.example.com"
  accessKey: "minioadmin"
  secretKey: "minioadmin"
  bucket: "control-panel"
  region: "us-east-1"
  usePathStyle: true  # MinIO requires path-style URLs
```

### DigitalOcean Spaces

```yaml
s3:
  enabled: true
  endpoint: "https://nyc3.digitaloceanspaces.com"
  accessKey: "DO00ABCDEFGHIJKLMNO"
  secretKey: "ABC123xyz789example"
  bucket: "my-space-name"
  region: "nyc3"
  usePathStyle: false
```

### Backblaze B2

```yaml
s3:
  enabled: true
  endpoint: "https://s3.us-west-002.backblazeb2.com"
  accessKey: "000abcd1234567890000000001"
  secretKey: "K000xyz987654321abcdefghijklmnopqr"
  bucket: "my-bucket-name"
  region: "us-west-002"
  usePathStyle: false
```

### Cloudflare R2

```yaml
s3:
  enabled: true
  endpoint: "https://<account-id>.r2.cloudflarestorage.com"
  accessKey: "your-r2-access-key"
  secretKey: "your-r2-secret-key"
  bucket: "control-panel-storage"
  region: "auto"
  usePathStyle: false
```

## Using S3 with Helm

### Installing Mail Services with S3

```bash
helm install mail-services ./helm/mail-services \
  --namespace control-panel \
  --set s3.enabled=true \
  --set s3.endpoint="$S3_ENDPOINT" \
  --set s3.accessKey="$S3_ACCESS_KEY" \
  --set s3.secretKey="$S3_SECRET_KEY" \
  --set s3.bucket="mail-storage" \
  --set s3.region="$S3_REGION"
```

### Installing DNS Cluster with S3

```bash
helm install dns-cluster ./helm/dns-cluster \
  --namespace control-panel \
  --set s3.enabled=true \
  --set s3.endpoint="$S3_ENDPOINT" \
  --set s3.accessKey="$S3_ACCESS_KEY" \
  --set s3.secretKey="$S3_SECRET_KEY" \
  --set s3.bucket="dns-storage" \
  --set s3.region="$S3_REGION"
```

### Installing MariaDB with S3 Storage

When using the automated installation script (`install-control-panel.sh`), MariaDB is automatically configured to use S3 storage if enabled. For manual installation:

```bash
helm install mariadb bitnami/mariadb \
  --namespace control-panel \
  --set auth.rootPassword="secure-password" \
  --set auth.database=controlpanel \
  --set auth.username=controlpanel \
  --set auth.password="secure-password" \
  --set primary.persistence.enabled=true \
  --set primary.persistence.size=20Gi \
  --set primary.persistence.storageClass="s3-storage" \
  --set architecture=replication \
  --set secondary.replicaCount=2 \
  --set secondary.persistence.storageClass="s3-storage" \
  --set metrics.enabled=true
```

**Note**: For MariaDB with S3 storage, ensure your S3-compatible storage supports block storage mode or use a CSI driver that provides block device emulation over S3 (like [MinIO DirectPV](https://github.com/minio/directpv) or [S3FS-FUSE](https://github.com/s3fs-fuse/s3fs-fuse) with appropriate configuration).

### Updating Existing Installation

```bash
# Update values.yaml or use --set flags
helm upgrade control-panel ./helm/control-panel \
  --namespace control-panel \
  --reuse-values \
  --set s3.enabled=true \
  --set s3.endpoint="https://s3.amazonaws.com" \
  --set s3.accessKey="NEW_ACCESS_KEY" \
  --set s3.secretKey="NEW_SECRET_KEY"
```

## Troubleshooting

### Common Issues

#### 1. Connection Timeout

**Symptom**: Pods fail to start or show S3 connection errors

**Solution**:
```bash
# Verify endpoint is reachable from cluster
kubectl run -it --rm debug --image=curlimages/curl --restart=Never -- curl -v $S3_ENDPOINT

# Check network policies
kubectl get networkpolicies -n control-panel
```

#### 2. Access Denied Errors

**Symptom**: 403 Forbidden or Access Denied errors

**Solution**:
- Verify access key and secret key are correct
- Check bucket permissions/policies
- Ensure the IAM user/role has required permissions:
  ```json
  {
    "Version": "2012-10-17",
    "Statement": [
      {
        "Effect": "Allow",
        "Action": [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject",
          "s3:ListBucket"
        ],
        "Resource": [
          "arn:aws:s3:::your-bucket-name",
          "arn:aws:s3:::your-bucket-name/*"
        ]
      }
    ]
  }
  ```

#### 3. Path Style Endpoint Issues

**Symptom**: Bucket not found or DNS resolution errors

**Solution**:
```yaml
# For MinIO and some S3-compatible services, enable path-style
s3:
  usePathStyle: true
```

#### 4. SSL/TLS Certificate Errors

**Symptom**: Certificate verification failed

**Solution**:
- Ensure endpoint URL uses HTTPS
- For self-signed certificates, you may need to configure trust
- For MinIO: `--set s3.endpoint="https://minio.example.com"`

### Debug Commands

```bash
# Check pod logs
kubectl logs -n control-panel deployment/control-panel -c php-fpm

# View environment variables in pod
kubectl exec -n control-panel deployment/control-panel -c php-fpm -- env | grep AWS

# Test S3 access from pod
kubectl exec -it -n control-panel deployment/control-panel -c php-fpm -- php artisan tinker
# In tinker:
# Storage::disk('s3')->put('test.txt', 'Hello World');
# Storage::disk('s3')->get('test.txt');

# Describe secrets
kubectl describe secret control-panel-secrets -n control-panel
```

## Best Practices

### Security

1. **Use IAM Roles (AWS)**: Instead of access keys, use IAM roles for service accounts (IRSA)
2. **Rotate Credentials**: Regularly rotate access keys and update secrets
3. **Least Privilege**: Grant only necessary S3 permissions
4. **Encrypt at Rest**: Enable server-side encryption on S3 bucket
5. **Use TLS/SSL**: Always use HTTPS endpoints
6. **Secrets Management**: Never commit credentials to version control

### Performance

1. **Choose Nearby Region**: Select an S3 region close to your Kubernetes cluster
2. **Use CDN**: Configure CloudFront or similar CDN for public assets
3. **Implement Caching**: Use Redis or similar for frequently accessed data
4. **Multipart Upload**: Configure for large files (handled by Laravel automatically)
5. **Lifecycle Policies**: Automatically transition or expire old files

### Cost Optimization

1. **Storage Classes**: Use appropriate storage class (Standard, IA, Glacier)
2. **Lifecycle Rules**: Move old data to cheaper storage tiers
3. **Monitor Usage**: Set up billing alerts and monitor storage metrics
4. **Delete Unused Data**: Implement cleanup policies for temporary files
5. **Compression**: Enable compression for text-based files

### Reliability

1. **Enable Versioning**: Protect against accidental deletions
2. **Cross-Region Replication**: For disaster recovery
3. **Monitoring**: Set up CloudWatch or monitoring for your S3 service
4. **Backup Strategy**: Regular backups even with S3 durability
5. **Test Restores**: Regularly test data restoration procedures

### Application Configuration

Update your Laravel configuration in `.env`:

```bash
# Set S3 as default filesystem
FILESYSTEM_DISK=s3

# S3 credentials (automatically set by Helm chart)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_ENDPOINT=https://s3.amazonaws.com
AWS_USE_PATH_STYLE_ENDPOINT=false

# Optional: Custom URL for public files
AWS_URL=https://cdn.example.com
```

## Migrating to S3

If you're migrating from local storage to S3:

### Step 1: Backup Current Data

```bash
# Create backup of current storage
kubectl cp control-panel/control-panel-xxx:/var/www/html/storage ./storage-backup
```

### Step 2: Enable S3 Configuration

```bash
helm upgrade control-panel ./helm/control-panel \
  --namespace control-panel \
  --reuse-values \
  --set s3.enabled=true \
  --set s3.endpoint="$S3_ENDPOINT" \
  --set s3.accessKey="$S3_ACCESS_KEY" \
  --set s3.secretKey="$S3_SECRET_KEY" \
  --set s3.bucket="$S3_BUCKET" \
  --set s3.region="$S3_REGION"
```

### Step 3: Migrate Data

```bash
# Upload existing files to S3
kubectl exec -n control-panel deployment/control-panel -c php-fpm -- \
  php artisan storage:migrate-to-s3
```

### Step 4: Verify and Clean Up

```bash
# Verify files are accessible
# Test application functionality
# Remove local storage after verification
```

## Monitoring S3 Usage

### AWS CloudWatch

```bash
# Enable S3 metrics in CloudWatch
aws s3api put-bucket-metrics-configuration \
  --bucket your-bucket-name \
  --id EntireBucket \
  --metrics-configuration Status=Enabled
```

### Kubernetes Metrics

```bash
# Monitor pod resource usage
kubectl top pod -n control-panel

# View detailed metrics
kubectl get --raw /apis/metrics.k8s.io/v1beta1/namespaces/control-panel/pods
```

## Support

For additional help:
- **Documentation**: https://liberu.co.uk
- **GitHub Issues**: https://github.com/liberu-control-panel/control-panel-laravel/issues
- **Community**: GitHub Discussions

## See Also

- [Kubernetes Setup Guide](KUBERNETES_SETUP.md)
- [Complete Kubernetes Installation](KUBERNETES_INSTALLATION.md)
- [Security Best Practices](SECURITY.md)
- [Helm Chart Documentation](../helm/control-panel/README.md)
