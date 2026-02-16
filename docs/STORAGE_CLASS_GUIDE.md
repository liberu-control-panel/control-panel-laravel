# Storage Class Selection Guide for Managed Kubernetes

This guide helps you choose the right storage class for your Liberu Control Panel deployment on managed Kubernetes platforms.

## Table of Contents

- [Overview](#overview)
- [Storage Class Recommendations by Platform](#storage-class-recommendations-by-platform)
  - [AWS EKS](#aws-eks)
  - [Azure AKS](#azure-aks)
  - [Google GKE](#google-gke)
  - [DigitalOcean DOKS](#digitalocean-doks)
- [Storage Access Modes](#storage-access-modes)
- [Configuration Examples](#configuration-examples)
- [S3 Storage Alternative](#s3-storage-alternative)
- [Best Practices](#best-practices)

## Overview

The Control Panel helm charts support multiple storage backends:

1. **Managed Kubernetes Block Storage** (Recommended for most deployments)
   - Platform-specific persistent volumes (EBS, Azure Disk, GCE PD, etc.)
   - Best performance for databases and application storage
   - Native integration with cloud providers

2. **S3-Compatible Object Storage** (Alternative for shared storage)
   - AWS S3, MinIO, DigitalOcean Spaces, etc.
   - Good for shared file access across pods
   - Cost-effective for large file storage

3. **NFS/Shared Storage** (For ReadWriteMany requirements)
   - Required for mail services shared storage
   - Platform-specific options (EFS, Azure Files, Filestore)

## Storage Class Recommendations by Platform

### AWS EKS

#### Block Storage (ReadWriteOnce)

**For Application Storage:**
```yaml
persistence:
  storageClass: "gp3"  # General Purpose SSD v3 (Recommended)
  # Alternative: "gp2" (older generation, still supported)
```

**For Database Storage (High Performance):**
```yaml
mysql:
  primary:
    persistence:
      storageClass: "gp3"  # Good balance of performance and cost
      # Alternative: "io2" for very high IOPS requirements
```

**For Shared Storage (ReadWriteMany):**
```yaml
persistence:
  storageClass: "efs-sc"  # Amazon EFS via EFS CSI driver
  accessMode: ReadWriteMany
```

**Prerequisites:**
- EBS CSI driver (installed by default on newer EKS versions)
- For EFS: Install EFS CSI driver

**Installation:**
```bash
# Install EBS CSI driver (if not already installed)
kubectl apply -k "github.com/kubernetes-sigs/aws-ebs-csi-driver/deploy/kubernetes/overlays/stable/?ref=release-1.27"

# Install EFS CSI driver (for ReadWriteMany)
helm repo add aws-efs-csi-driver https://kubernetes-sigs.github.io/aws-efs-csi-driver/
helm install aws-efs-csi-driver aws-efs-csi-driver/aws-efs-csi-driver \
  --namespace kube-system
```

**Cost Comparison:**
- `gp3`: $0.08/GB-month + $0.005/provisioned IOPS
- `gp2`: $0.10/GB-month
- `io2`: $0.125/GB-month + $0.065/provisioned IOPS
- EFS: $0.30/GB-month (first TB)

---

### Azure AKS

#### Block Storage (ReadWriteOnce)

**For Application Storage:**
```yaml
persistence:
  storageClass: "managed-csi"  # Standard Azure Disk (Recommended)
```

**For Database Storage (High Performance):**
```yaml
mysql:
  primary:
    persistence:
      storageClass: "managed-csi-premium"  # Premium SSD for databases
```

**For Shared Storage (ReadWriteMany):**
```yaml
persistence:
  storageClass: "azurefile"  # Azure Files Standard
  # Alternative: "azurefile-premium" for better performance
  accessMode: ReadWriteMany
```

**Prerequisites:**
- Azure Disk CSI driver (installed by default)
- Azure Files CSI driver (installed by default)

**Storage Tiers:**
- `managed-csi` (Standard SSD): Up to 6,000 IOPS
- `managed-csi-premium` (Premium SSD): Up to 20,000 IOPS
- `azurefile`: Shared file storage (SMB)
- `azurefile-premium`: High-performance shared storage

**Cost Comparison:**
- Standard SSD: $0.10/GB-month
- Premium SSD: $0.135/GB-month
- Azure Files Standard: $0.06/GB-month
- Azure Files Premium: $0.20/GB-month

---

### Google GKE

#### Block Storage (ReadWriteOnce)

**For Application Storage:**
```yaml
persistence:
  storageClass: "pd-balanced"  # Balanced Persistent Disk (Recommended)
  # Alternative: "pd-standard" for cost savings
```

**For Database Storage (High Performance):**
```yaml
mysql:
  primary:
    persistence:
      storageClass: "pd-ssd"  # SSD Persistent Disk for databases
```

**For Shared Storage (ReadWriteMany):**
```yaml
persistence:
  storageClass: "filestore-nfs"  # Filestore via NFS CSI driver
  accessMode: ReadWriteMany
```

**Prerequisites:**
- Compute Engine persistent disk CSI driver (installed by default)
- For Filestore: Install Filestore CSI driver

**Installation:**
```bash
# Install Filestore CSI driver (for ReadWriteMany)
kubectl apply -f https://raw.githubusercontent.com/kubernetes-sigs/gcp-filestore-csi-driver/master/deploy/kubernetes/overlays/stable/deploy-driver.yaml
```

**Storage Types:**
- `pd-standard`: Standard HDD (baseline IOPS)
- `pd-balanced`: Balanced SSD (recommended)
- `pd-ssd`: High-performance SSD
- `filestore-nfs`: Managed NFS service

**Cost Comparison:**
- Standard PD: $0.04/GB-month
- Balanced PD: $0.10/GB-month
- SSD PD: $0.17/GB-month
- Filestore: $0.20/GB-month (1TB minimum)

---

### DigitalOcean DOKS

#### Block Storage (ReadWriteOnce)

**For All Storage (Application and Database):**
```yaml
persistence:
  storageClass: "do-block-storage"  # DigitalOcean Block Storage
```

**For Shared Storage (ReadWriteMany):**
DigitalOcean doesn't have a native ReadWriteMany solution. Options:
1. Use S3-compatible Spaces (recommended)
2. Deploy NFS server in the cluster
3. Use third-party storage solution (Longhorn, OpenEBS)

**Prerequisites:**
- DigitalOcean CSI driver (installed by default)

**Storage Options:**
- Block Storage: SSD-backed volumes
- Spaces: S3-compatible object storage

**Cost:**
- Block Storage: $0.10/GB-month
- Spaces: $5/month for 250GB + $0.02/GB beyond

---

## Storage Access Modes

| Access Mode | Description | Use Case | Support |
|-------------|-------------|----------|---------|
| **ReadWriteOnce (RWO)** | Volume can be mounted read-write by a single node | Databases, application storage | All platforms |
| **ReadWriteMany (RWX)** | Volume can be mounted read-write by many nodes | Shared mail storage, media files | Requires NFS or file storage |
| **ReadOnlyMany (ROX)** | Volume can be mounted read-only by many nodes | Rarely used | All platforms |

### Components and Their Requirements

| Component | Access Mode | Recommended Storage |
|-----------|-------------|---------------------|
| Control Panel App | RWO | gp3, managed-csi, pd-balanced |
| MySQL/MariaDB | RWO | gp3, managed-csi-premium, pd-ssd |
| Redis | In-memory (optional persistence) | Any RWO storage |
| Mail Storage (Dovecot) | RWO | gp3, managed-csi, pd-balanced |
| Mail Shared Storage | **RWX** | efs-sc, azurefile, filestore-nfs |
| DNS Database | RWO | gp3, managed-csi, pd-balanced |

---

## Configuration Examples

### Example 1: AWS EKS with EBS and EFS

```yaml
# helm/control-panel/values.yaml
persistence:
  enabled: true
  storageClass: "gp3"
  size: 10Gi

mysql:
  primary:
    persistence:
      storageClass: "gp3"
      size: 20Gi

# helm/mail-services/values.yaml
persistence:
  enabled: true
  storageClass: "efs-sc"  # For ReadWriteMany
  accessMode: ReadWriteMany
  size: 50Gi
```

### Example 2: Azure AKS with Managed Disks and Azure Files

```yaml
# helm/control-panel/values.yaml
persistence:
  enabled: true
  storageClass: "managed-csi"
  size: 10Gi

mysql:
  primary:
    persistence:
      storageClass: "managed-csi-premium"
      size: 20Gi

# helm/mail-services/values.yaml
persistence:
  enabled: true
  storageClass: "azurefile-premium"
  accessMode: ReadWriteMany
  size: 50Gi
```

### Example 3: Google GKE with Persistent Disks and Filestore

```yaml
# helm/control-panel/values.yaml
persistence:
  enabled: true
  storageClass: "pd-balanced"
  size: 10Gi

mysql:
  primary:
    persistence:
      storageClass: "pd-ssd"
      size: 20Gi

# helm/mail-services/values.yaml
persistence:
  enabled: true
  storageClass: "filestore-nfs"
  accessMode: ReadWriteMany
  size: 50Gi
```

### Example 4: DigitalOcean DOKS with Spaces for Shared Storage

```yaml
# helm/control-panel/values.yaml
persistence:
  enabled: true
  storageClass: "do-block-storage"
  size: 10Gi

mysql:
  primary:
    persistence:
      storageClass: "do-block-storage"
      size: 20Gi

# helm/mail-services/values.yaml
# Use S3-compatible Spaces instead of block storage
s3:
  enabled: true
  endpoint: "https://nyc3.digitaloceanspaces.com"
  accessKey: "YOUR_SPACES_KEY"
  secretKey: "YOUR_SPACES_SECRET"
  bucket: "mail-storage"
  region: "nyc3"
  usePathStyle: false
```

---

## S3 Storage Alternative

For scenarios where you need shared storage but don't want to use NFS-based solutions, you can use S3-compatible object storage.

### When to Use S3 Storage

✅ **Good for:**
- Shared file access across multiple pods
- Large file storage (media, backups)
- Cross-region data replication
- Cost-effective storage at scale

❌ **Not recommended for:**
- Database storage (requires block storage)
- High-frequency random I/O
- POSIX filesystem requirements

### Configuration Example

```yaml
s3:
  enabled: true
  endpoint: "https://s3.amazonaws.com"  # Or MinIO, Spaces, etc.
  accessKey: "AKIAIOSFODNN7EXAMPLE"
  secretKey: "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
  bucket: "control-panel-storage"
  region: "us-east-1"
  usePathStyle: false  # true for MinIO
```

**Supported S3 Services:**
- AWS S3
- DigitalOcean Spaces
- MinIO (self-hosted)
- Cloudflare R2
- Backblaze B2
- Wasabi

---

## Best Practices

### 1. Choose the Right Storage Tier

- **Databases**: Use premium/SSD storage for better IOPS
  - EKS: `gp3` or `io2`
  - AKS: `managed-csi-premium`
  - GKE: `pd-ssd`

- **Application Storage**: Balanced storage is usually sufficient
  - EKS: `gp3`
  - AKS: `managed-csi`
  - GKE: `pd-balanced`

### 2. Plan for ReadWriteMany Requirements

Mail services require shared storage (ReadWriteMany). Options by platform:

- **AWS**: Use EFS (`efs-sc`)
- **Azure**: Use Azure Files (`azurefile` or `azurefile-premium`)
- **GCP**: Use Filestore (`filestore-nfs`)
- **DigitalOcean**: Use Spaces (S3-compatible) or deploy NFS server

### 3. Consider Cost vs. Performance

| Performance Level | Use Case | AWS | Azure | GCP |
|-------------------|----------|-----|-------|-----|
| **Standard** | Development, testing | gp2 | managed-csi | pd-standard |
| **Balanced** | Production apps | gp3 | managed-csi | pd-balanced |
| **High** | Production databases | io2 | managed-csi-premium | pd-ssd |

### 4. Enable Automatic Volume Expansion

Most cloud storage classes support volume expansion. Add this to your storage class:

```yaml
allowVolumeExpansion: true
```

### 5. Use Volume Snapshots for Backups

Enable volume snapshots for disaster recovery:

```bash
# AWS EKS
kubectl create -f https://raw.githubusercontent.com/kubernetes-sigs/aws-ebs-csi-driver/master/examples/kubernetes/snapshot/manifests/classes/snapshotclass.yaml

# Azure AKS
kubectl create -f https://raw.githubusercontent.com/kubernetes-sigs/azuredisk-csi-driver/master/deploy/example/snapshot/storageclass-azuredisk-snapshot.yaml

# Google GKE
kubectl create -f https://raw.githubusercontent.com/kubernetes-sigs/gcp-compute-persistent-disk-csi-driver/master/examples/kubernetes/snapshot/snapshot-class.yaml
```

### 6. Monitor Storage Performance

Use platform-specific monitoring:
- **AWS**: CloudWatch metrics for EBS volumes
- **Azure**: Azure Monitor for Disk metrics
- **GCP**: Cloud Monitoring for Persistent Disk metrics

### 7. Encryption at Rest

All major cloud providers support encryption at rest:
- **AWS**: Enable via storage class parameter `encrypted: "true"`
- **Azure**: Enabled by default for managed disks
- **GCP**: Enabled by default for persistent disks

---

## Troubleshooting

### Issue: PVC Stuck in Pending

**Cause**: Storage class not available or incorrect

**Solution**:
```bash
# Check available storage classes
kubectl get storageclass

# Check PVC status
kubectl describe pvc <pvc-name>

# Verify CSI driver is running
kubectl get pods -n kube-system | grep csi
```

### Issue: ReadWriteMany Not Supported

**Cause**: Using block storage for RWX requirement

**Solution**: Switch to NFS-based storage or S3:
- EKS: Use EFS
- AKS: Use Azure Files
- GKE: Use Filestore
- DOKS: Use Spaces or NFS

### Issue: Performance Issues

**Solutions**:
1. Upgrade to premium storage tier
2. Increase provisioned IOPS (AWS io2, Azure Premium)
3. Check for IOPS throttling in cloud provider console
4. Consider using local SSDs for temporary high-performance needs

---

## Summary

| Platform | App Storage | Database Storage | Shared Storage (RWX) |
|----------|-------------|------------------|----------------------|
| **AWS EKS** | gp3 | gp3 or io2 | efs-sc |
| **Azure AKS** | managed-csi | managed-csi-premium | azurefile-premium |
| **Google GKE** | pd-balanced | pd-ssd | filestore-nfs |
| **DigitalOcean** | do-block-storage | do-block-storage | Spaces (S3) or NFS |

For most production deployments, use platform-specific managed storage for best performance and integration. S3-compatible storage is a good alternative for shared file storage when cost is a concern.
