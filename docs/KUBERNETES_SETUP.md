# Kubernetes Setup Guide

This guide walks you through setting up a Kubernetes cluster for use with the Liberu Control Panel.

## Prerequisites

- Kubernetes cluster (v1.20 or higher)
- kubectl CLI tool installed
- Cluster admin access
- Basic knowledge of Kubernetes concepts

## Required Components

### 1. NGINX Ingress Controller

The control panel requires an Ingress controller to route traffic to deployed applications.

#### Install NGINX Ingress Controller

```bash
# Using Helm
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update
helm install ingress-nginx ingress-nginx/ingress-nginx \
  --namespace ingress-nginx \
  --create-namespace

# Or using kubectl
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.8.1/deploy/static/provider/cloud/deploy.yaml
```

Verify installation:

```bash
kubectl get pods -n ingress-nginx
kubectl get svc -n ingress-nginx
```

### 2. cert-manager (Optional but Recommended)

cert-manager automates SSL/TLS certificate management with Let's Encrypt.

#### Install cert-manager

```bash
# Using Helm
helm repo add jetstack https://charts.jetstack.io
helm repo update
helm install cert-manager jetstack/cert-manager \
  --namespace cert-manager \
  --create-namespace \
  --set installCRDs=true

# Or using kubectl
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml
```

#### Create ClusterIssuer for Let's Encrypt

```yaml
# letsencrypt-prod.yaml
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@example.com  # Change this
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
```

```bash
kubectl apply -f letsencrypt-prod.yaml
```

### 3. Storage Provisioner

You need a storage provisioner for persistent volumes.

#### For Local/Development

```bash
# Install local-path-provisioner
kubectl apply -f https://raw.githubusercontent.com/rancher/local-path-provisioner/master/deploy/local-path-storage.yaml

# Set as default storage class
kubectl patch storageclass local-path -p '{"metadata": {"annotations":{"storageclass.kubernetes.io/is-default-class":"true"}}}'
```

#### For Cloud Providers

Most cloud providers have built-in storage provisioners:
- **GKE**: `pd-standard`, `pd-ssd`
- **EKS**: `gp2`, `gp3`
- **AKS**: `default`, `managed-premium`

## Server Configuration

### SSH User Setup

Create a dedicated user for the control panel:

```bash
# Create user
sudo useradd -m -s /bin/bash controlpanel

# Copy kubeconfig
sudo mkdir -p /home/controlpanel/.kube
sudo cp ~/.kube/config /home/controlpanel/.kube/
sudo chown -R controlpanel:controlpanel /home/controlpanel/.kube

# Test kubectl access
sudo -u controlpanel kubectl get nodes
```

### RBAC for Control Panel User (Optional)

For better security, create limited permissions:

```yaml
# controlpanel-rbac.yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: controlpanel
  namespace: default
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: controlpanel-role
rules:
- apiGroups: [""]
  resources: ["namespaces"]
  verbs: ["get", "list", "create", "delete"]
- apiGroups: [""]
  resources: ["pods", "services", "persistentvolumeclaims", "configmaps", "secrets"]
  verbs: ["*"]
- apiGroups: ["apps"]
  resources: ["deployments", "statefulsets"]
  verbs: ["*"]
- apiGroups: ["networking.k8s.io"]
  resources: ["ingresses", "networkpolicies"]
  verbs: ["*"]
- apiGroups: ["rbac.authorization.k8s.io"]
  resources: ["roles", "rolebindings"]
  verbs: ["*"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  name: controlpanel-binding
subjects:
- kind: ServiceAccount
  name: controlpanel
  namespace: default
roleRef:
  kind: ClusterRole
  name: controlpanel-role
  apiGroup: rbac.authorization.k8s.io
```

```bash
kubectl apply -f controlpanel-rbac.yaml
```

## Control Panel Configuration

### Environment Variables

Configure in `.env`:

```env
# Kubernetes Settings
KUBERNETES_ENABLED=true
KUBECTL_PATH=/usr/local/bin/kubectl
KUBERNETES_NAMESPACE_PREFIX=hosting-
KUBERNETES_INGRESS_CLASS=nginx
KUBERNETES_CERT_ISSUER=letsencrypt-prod
KUBERNETES_STORAGE_CLASS=standard

# Default Resource Limits
KUBERNETES_DEFAULT_MEMORY_REQUEST=128Mi
KUBERNETES_DEFAULT_CPU_REQUEST=100m
KUBERNETES_DEFAULT_MEMORY_LIMIT=512Mi
KUBERNETES_DEFAULT_CPU_LIMIT=500m

# Container Images
KUBERNETES_IMAGE_NGINX=nginx:alpine
KUBERNETES_IMAGE_PHP_FPM=php:8.2-fpm-alpine
KUBERNETES_IMAGE_MYSQL=mysql:8.0
KUBERNETES_IMAGE_REDIS=redis:alpine

# Security
KUBERNETES_ENABLE_PSP=true
KUBERNETES_ENABLE_NETWORK_POLICIES=true
KUBERNETES_RUN_AS_NON_ROOT=true
```

### Test Deployment

Create a test server in the control panel:

1. Navigate to **Servers** → **Create Server**
2. Fill in:
   - Name: Production K8s
   - Hostname: your-k8s-server.com
   - Type: Kubernetes
   - SSH credentials
3. Click **Test Connection**

Then create a test domain to verify deployment works.

## Namespace Structure

Each domain gets its own namespace:

```
hosting-example-com/
├── Deployment: example-com
├── Service: example-com
├── Ingress: example-com
├── ConfigMap: example-com-nginx
├── Secret: example-com-credentials
├── PVC: example-com-web
└── NetworkPolicy: isolation rules
```

## Security Policies Applied

### 1. RBAC (Role-Based Access Control)

Each namespace gets:
- ServiceAccount for the application
- Role with limited permissions
- RoleBinding

### 2. NetworkPolicies

- Deny all ingress by default
- Allow ingress from NGINX controller
- Allow internal namespace communication
- Allow DNS egress

### 3. ResourceQuota

Limits per namespace:
- CPU: 2 cores request, 4 cores limit
- Memory: 4Gi request, 8Gi limit
- Pods: 20
- Services: 10
- PVCs: 10

### 4. LimitRange

Default per container:
- Memory: 128Mi request, 512Mi limit
- CPU: 100m request, 500m limit

## Monitoring and Logging

### View Pod Logs

```bash
kubectl logs -n hosting-example-com example-com-xyz
```

### Check Pod Status

```bash
kubectl get pods -n hosting-example-com
kubectl describe pod -n hosting-example-com example-com-xyz
```

### Monitor Resources

```bash
kubectl top pods -n hosting-example-com
kubectl top nodes
```

## Troubleshooting

### Pods Not Starting

**Check events:**
```bash
kubectl get events -n hosting-example-com
```

**Common issues:**
- Image pull errors (check image name)
- Resource limits too low
- Volume mount errors

### Ingress Not Working

**Check ingress:**
```bash
kubectl describe ingress -n hosting-example-com
```

**Common issues:**
- cert-manager not installed
- DNS not pointing to ingress controller
- Ingress class mismatch

### Permission Errors

**Check RBAC:**
```bash
kubectl auth can-i create pods -n hosting-example-com --as=system:serviceaccount:default:controlpanel
```

**Check user permissions:**
```bash
kubectl describe clusterrolebinding controlpanel-binding
```

## Performance Tuning

### Resource Limits

Adjust default limits in `config/kubernetes.php`:

```php
'default_resources' => [
    'requests' => [
        'memory' => '256Mi',  // Increase for larger apps
        'cpu' => '200m',
    ],
    'limits' => [
        'memory' => '1Gi',
        'cpu' => '1000m',
    ],
],
```

### Horizontal Pod Autoscaling (Advanced)

Enable HPA for auto-scaling:

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: example-com-hpa
  namespace: hosting-example-com
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: example-com
  minReplicas: 1
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
```

## Best Practices

1. **Use namespaces** - Keep each customer's resources isolated
2. **Set resource limits** - Prevent resource exhaustion
3. **Enable network policies** - Isolate network traffic
4. **Use TLS** - Always enable cert-manager for HTTPS
5. **Monitor resources** - Set up Prometheus/Grafana
6. **Regular backups** - Backup PVCs regularly
7. **Update images** - Keep container images up-to-date
8. **Limit permissions** - Use RBAC for least privilege

## Advanced Topics

### Multi-Cluster Support

For enterprise deployments, you can connect to multiple Kubernetes clusters:

1. Add multiple servers in control panel
2. Each with its own kubeconfig
3. Load balance domains across clusters

### Custom Storage Classes

Create specialized storage classes:

```yaml
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: fast-ssd
provisioner: kubernetes.io/gce-pd
parameters:
  type: pd-ssd
```

### Custom Ingress Annotations

Customize ingress behavior per domain:

```yaml
metadata:
  annotations:
    nginx.ingress.kubernetes.io/rate-limit: "100"
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
```

## References

- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [NGINX Ingress Controller](https://kubernetes.github.io/ingress-nginx/)
- [cert-manager Documentation](https://cert-manager.io/docs/)
- [Kubernetes Best Practices](https://kubernetes.io/docs/concepts/configuration/overview/)
