# Managed Kubernetes Setup Guide

This guide covers deploying the Liberu Control Panel on managed Kubernetes services from cloud providers including AWS EKS, Azure AKS, Google GKE, and DigitalOcean DOKS.

## Table of Contents

1. [Overview](#overview)
2. [AWS EKS Setup](#aws-eks-setup)
3. [Azure AKS Setup](#azure-aks-setup)
4. [Google GKE Setup](#google-gke-setup)
5. [DigitalOcean DOKS Setup](#digitalocean-doks-setup)
6. [Common Post-Setup Steps](#common-post-setup-steps)
7. [Managed Service Advantages](#managed-service-advantages)
8. [Cost Optimization](#cost-optimization)

## Overview

Managed Kubernetes services handle the complexity of running and maintaining Kubernetes control planes, allowing you to focus on deploying and managing your applications. The control panel works seamlessly with all major managed Kubernetes providers.

### What's Managed vs. Self-Managed

**Managed by Cloud Provider:**
- Kubernetes control plane (API server, etcd, scheduler, controller manager)
- Control plane high availability and upgrades
- Control plane security patches
- etcd backups

**You Manage:**
- Worker nodes (can be auto-managed with node pools)
- Applications and workloads
- Ingress controllers
- Storage classes
- Monitoring and logging

### Prerequisites for All Providers

- Cloud provider account with billing enabled
- Command-line tools installed (kubectl, cloud provider CLI)
- Domain name with DNS management access
- Basic Kubernetes knowledge

## AWS EKS Setup

### Prerequisites

```bash
# Install AWS CLI
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Install eksctl
curl --silent --location "https://github.com/weaveworks/eksctl/releases/latest/download/eksctl_$(uname -s)_amd64.tar.gz" | tar xz -C /tmp
sudo mv /tmp/eksctl /usr/local/bin

# Configure AWS credentials
aws configure
```

### Create EKS Cluster

```bash
# Create cluster with eksctl (recommended)
eksctl create cluster \
  --name control-panel-cluster \
  --region us-west-2 \
  --nodegroup-name standard-workers \
  --node-type t3.medium \
  --nodes 3 \
  --nodes-min 2 \
  --nodes-max 5 \
  --managed

# Or using AWS Console:
# 1. Navigate to EKS service
# 2. Click "Create cluster"
# 3. Configure cluster settings
# 4. Add node group
```

### Configure kubectl

```bash
# Update kubeconfig
aws eks update-kubeconfig --region us-west-2 --name control-panel-cluster

# Verify connection
kubectl get nodes
```

### Install AWS Load Balancer Controller

```bash
# Create IAM policy
curl -o iam_policy.json https://raw.githubusercontent.com/kubernetes-sigs/aws-load-balancer-controller/v2.7.0/docs/install/iam_policy.json
aws iam create-policy \
  --policy-name AWSLoadBalancerControllerIAMPolicy \
  --policy-document file://iam_policy.json

# Install with Helm
helm repo add eks https://aws.github.io/eks-charts
helm repo update
helm install aws-load-balancer-controller eks/aws-load-balancer-controller \
  -n kube-system \
  --set clusterName=control-panel-cluster
```

### EKS Storage Configuration

EBS CSI driver for persistent volumes:

```bash
# Install EBS CSI driver
kubectl apply -k "github.com/kubernetes-sigs/aws-ebs-csi-driver/deploy/kubernetes/overlays/stable/?ref=release-1.27"

# Create storage class
cat <<EOF | kubectl apply -f -
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: gp3
  annotations:
    storageclass.kubernetes.io/is-default-class: "true"
provisioner: ebs.csi.aws.com
parameters:
  type: gp3
  encrypted: "true"
volumeBindingMode: WaitForFirstConsumer
EOF
```

### Deploy Control Panel on EKS

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Install with Helm
helm install control-panel ./helm/control-panel \
  --set ingress.className=alb \
  --set ingress.annotations."alb\.ingress\.kubernetes\.io/scheme"=internet-facing \
  --set ingress.annotations."alb\.ingress\.kubernetes\.io/target-type"=ip \
  --namespace control-panel \
  --create-namespace
```

## Azure AKS Setup

### Prerequisites

```bash
# Install Azure CLI
curl -sL https://aka.ms/InstallAzureCLIDeb | sudo bash

# Login to Azure
az login

# Set subscription
az account set --subscription "Your-Subscription-Name"
```

### Create AKS Cluster

```bash
# Create resource group
az group create --name control-panel-rg --location eastus

# Create AKS cluster
az aks create \
  --resource-group control-panel-rg \
  --name control-panel-cluster \
  --node-count 3 \
  --node-vm-size Standard_D2s_v3 \
  --enable-managed-identity \
  --enable-addons monitoring \
  --generate-ssh-keys

# Get credentials
az aks get-credentials --resource-group control-panel-rg --name control-panel-cluster

# Verify connection
kubectl get nodes
```

### Install NGINX Ingress Controller for AKS

```bash
# Install NGINX Ingress
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update
helm install ingress-nginx ingress-nginx/ingress-nginx \
  --namespace ingress-nginx \
  --create-namespace \
  --set controller.service.annotations."service\.beta\.kubernetes\.io/azure-load-balancer-health-probe-request-path"=/healthz

# Get external IP
kubectl get service -n ingress-nginx ingress-nginx-controller
```

### AKS Storage Configuration

Azure Disk storage class (already available):

```bash
# List available storage classes
kubectl get storageclass

# Set managed-premium as default (optional)
kubectl patch storageclass managed-premium -p '{"metadata": {"annotations":{"storageclass.kubernetes.io/is-default-class":"true"}}}'
```

### Deploy Control Panel on AKS

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Install with Helm
helm install control-panel ./helm/control-panel \
  --set ingress.className=nginx \
  --set storage.storageClassName=managed-premium \
  --namespace control-panel \
  --create-namespace
```

## Google GKE Setup

### Prerequisites

```bash
# Install gcloud CLI
curl https://sdk.cloud.google.com | bash
exec -l $SHELL

# Initialize gcloud
gcloud init

# Install kubectl
gcloud components install kubectl
```

### Create GKE Cluster

```bash
# Set project
gcloud config set project YOUR_PROJECT_ID

# Create cluster
gcloud container clusters create control-panel-cluster \
  --region us-central1 \
  --num-nodes 3 \
  --machine-type n1-standard-2 \
  --enable-autoscaling \
  --min-nodes 2 \
  --max-nodes 5 \
  --enable-autorepair \
  --enable-autoupgrade

# Get credentials
gcloud container clusters get-credentials control-panel-cluster --region us-central1

# Verify connection
kubectl get nodes
```

### Install NGINX Ingress Controller for GKE

```bash
# Install NGINX Ingress
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update
helm install ingress-nginx ingress-nginx/ingress-nginx \
  --namespace ingress-nginx \
  --create-namespace

# Get external IP
kubectl get service -n ingress-nginx ingress-nginx-controller
```

### GKE Storage Configuration

GKE provides default storage classes:

```bash
# List storage classes
kubectl get storageclass

# standard (default) - HDD-based
# standard-rwo - SSD-based
# premium-rwo - High-performance SSD

# Set premium-rwo as default (optional)
kubectl patch storageclass premium-rwo -p '{"metadata": {"annotations":{"storageclass.kubernetes.io/is-default-class":"true"}}}'
```

### Deploy Control Panel on GKE

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Install with Helm
helm install control-panel ./helm/control-panel \
  --set ingress.className=nginx \
  --namespace control-panel \
  --create-namespace
```

## DigitalOcean DOKS Setup

### Prerequisites

```bash
# Install doctl
cd ~
wget https://github.com/digitalocean/doctl/releases/download/v1.100.0/doctl-1.100.0-linux-amd64.tar.gz
tar xf ~/doctl-1.100.0-linux-amd64.tar.gz
sudo mv ~/doctl /usr/local/bin

# Authenticate
doctl auth init
```

### Create DOKS Cluster

```bash
# Create cluster
doctl kubernetes cluster create control-panel-cluster \
  --region nyc1 \
  --version 1.29.0-do.0 \
  --node-pool "name=worker-pool;size=s-2vcpu-4gb;count=3;auto-scale=true;min-nodes=2;max-nodes=5"

# Get credentials (automatic)
doctl kubernetes cluster kubeconfig save control-panel-cluster

# Verify connection
kubectl get nodes
```

### Install NGINX Ingress Controller for DOKS

```bash
# Install NGINX Ingress
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update
helm install ingress-nginx ingress-nginx/ingress-nginx \
  --namespace ingress-nginx \
  --create-namespace \
  --set controller.service.annotations."service\.beta\.kubernetes\.io/do-loadbalancer-name"=control-panel-lb

# Get external IP
kubectl get service -n ingress-nginx ingress-nginx-controller
```

### DOKS Storage Configuration

DigitalOcean provides a default storage class:

```bash
# List storage classes
kubectl get storageclass

# do-block-storage is the default
# Verify it's set as default
kubectl get storageclass do-block-storage -o yaml
```

### Deploy Control Panel on DOKS

```bash
# Clone repository
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel

# Install with Helm
helm install control-panel ./helm/control-panel \
  --set ingress.className=nginx \
  --namespace control-panel \
  --create-namespace
```

## Common Post-Setup Steps

These steps apply to all managed Kubernetes providers after cluster creation.

### 1. Install cert-manager

```bash
# Install cert-manager for Let's Encrypt
helm repo add jetstack https://charts.jetstack.io
helm repo update
helm install cert-manager jetstack/cert-manager \
  --namespace cert-manager \
  --create-namespace \
  --set installCRDs=true

# Create Let's Encrypt ClusterIssuers
cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: your-email@example.com
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
---
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-staging
spec:
  acme:
    server: https://acme-staging-v02.api.letsencrypt.org/directory
    email: your-email@example.com
    privateKeySecretRef:
      name: letsencrypt-staging
    solvers:
    - http01:
        ingress:
          class: nginx
EOF
```

### 2. Install Metrics Server

Most managed services include metrics-server, but verify:

```bash
# Check if metrics-server exists
kubectl get deployment metrics-server -n kube-system

# If not present, install it
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
```

### 3. Configure DNS

Get the Ingress external IP and point your domain:

```bash
# Get external IP
EXTERNAL_IP=$(kubectl get service -n ingress-nginx ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
echo "Point your domain to: $EXTERNAL_IP"

# Create DNS A record
# control.yourdomain.com -> $EXTERNAL_IP
```

### 4. Deploy the Control Panel

```bash
# Set configuration variables
export DOMAIN=control.yourdomain.com
export LETSENCRYPT_EMAIL=admin@yourdomain.com

# Create values file
cat > values-production.yaml <<EOF
app:
  env: production
  url: https://$DOMAIN

ingress:
  className: nginx
  hosts:
    - host: $DOMAIN
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: control-panel-tls
      hosts:
        - $DOMAIN
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod

mysql:
  auth:
    password: $(openssl rand -base64 32)
    rootPassword: $(openssl rand -base64 32)
EOF

# Install
helm install control-panel ./helm/control-panel \
  -f values-production.yaml \
  --namespace control-panel \
  --create-namespace
```

### 5. Create Admin User

```bash
# Wait for pods to be ready
kubectl wait --for=condition=available --timeout=300s \
  deployment/control-panel -n control-panel

# Run migrations
kubectl exec -n control-panel deployment/control-panel -c php-fpm -- \
  php artisan migrate --force

# Create admin user
kubectl exec -it -n control-panel deployment/control-panel -c php-fpm -- \
  php artisan make:filament-user
```

## Managed Service Advantages

### Automatic Control Plane Management

- **No manual etcd management** - Cloud provider handles backups and high availability
- **Automatic upgrades** - Control plane can be upgraded with minimal downtime
- **Security patches** - Automatic security updates for control plane components
- **High availability** - Multi-AZ control plane deployment by default

### Simplified Operations

- **Managed add-ons** - Cloud providers offer managed versions of common tools
- **Native integrations** - Seamless integration with cloud storage, load balancers, IAM
- **Auto-scaling** - Node auto-scaling based on workload demands
- **Monitoring** - Built-in integration with cloud monitoring services

### Cost Benefits

- **No control plane costs** - Some providers offer free control plane (you only pay for workers)
- **Pay-as-you-go** - Scale nodes up and down based on demand
- **Reserved instances** - Save costs with long-term commitments
- **Spot instances** - Use spot/preemptible instances for non-critical workloads

## Cost Optimization

### General Tips

1. **Right-size nodes** - Choose appropriate instance types for workload
2. **Use auto-scaling** - Scale nodes based on actual usage
3. **Use spot instances** - For stateless workloads and dev environments
4. **Resource requests/limits** - Set appropriate requests to optimize bin-packing
5. **Storage optimization** - Use appropriate storage classes for different workloads

### Provider-Specific Optimization

**AWS EKS:**
- Use Fargate for serverless pods (no node management)
- Leverage Spot instances for worker nodes
- Use Savings Plans or Reserved Instances

**Azure AKS:**
- Free control plane (no charge for Kubernetes management)
- Use Azure Spot VMs for worker nodes
- Enable cluster auto-scaler

**Google GKE:**
- Autopilot mode for fully managed nodes
- Use Preemptible VMs for worker nodes
- Committed use discounts

**DigitalOcean DOKS:**
- No control plane charges
- Simpler, more predictable pricing
- Auto-scaling node pools

### Monitoring Costs

```bash
# Monitor resource usage
kubectl top nodes
kubectl top pods --all-namespaces

# Check for over-provisioned resources
kubectl get pods --all-namespaces -o json | \
  jq '.items[] | {name: .metadata.name, namespace: .metadata.namespace, requests: .spec.containers[].resources.requests, limits: .spec.containers[].resources.limits}'
```

## Security Best Practices

1. **Enable RBAC** - Use role-based access control
2. **Network policies** - Restrict pod-to-pod communication
3. **Pod security** - Use Pod Security Standards
4. **Secrets management** - Use cloud provider secret managers
5. **Image scanning** - Scan container images for vulnerabilities
6. **Audit logging** - Enable Kubernetes audit logs
7. **Regular updates** - Keep clusters and node pools updated

## Backup and Disaster Recovery

### Database Backups

```bash
# Create backup CronJob
cat <<EOF | kubectl apply -f -
apiVersion: batch/v1
kind: CronJob
metadata:
  name: mysql-backup
  namespace: control-panel
spec:
  schedule: "0 2 * * *"  # Daily at 2 AM
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: backup
            image: mysql:8.0
            command:
            - /bin/sh
            - -c
            - mysqldump -h mariadb -u root -p\$MYSQL_ROOT_PASSWORD --all-databases > /backup/backup-\$(date +%Y%m%d).sql
            env:
            - name: MYSQL_ROOT_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: mariadb-secret
                  key: root-password
            volumeMounts:
            - name: backup
              mountPath: /backup
          volumes:
          - name: backup
            persistentVolumeClaim:
              claimName: mysql-backup-pvc
          restartPolicy: OnFailure
EOF
```

### Velero for Cluster Backups

```bash
# Install Velero for complete cluster backups
# See: https://velero.io/docs/

# Example for AWS
velero install \
  --provider aws \
  --plugins velero/velero-plugin-for-aws:v1.9.0 \
  --bucket velero-backups \
  --backup-location-config region=us-west-2 \
  --snapshot-location-config region=us-west-2
```

## Troubleshooting

### Common Issues

**Pods stuck in Pending:**
```bash
kubectl describe pod <pod-name> -n control-panel
# Usually indicates resource constraints or storage issues
```

**Ingress not getting external IP:**
```bash
kubectl describe service -n ingress-nginx ingress-nginx-controller
# Check cloud provider load balancer status in cloud console
```

**Certificate not issuing:**
```bash
kubectl describe certificate -n control-panel
kubectl describe certificaterequest -n control-panel
kubectl logs -n cert-manager deployment/cert-manager
```

### Getting Help

- **AWS EKS**: AWS Support, EKS documentation
- **Azure AKS**: Azure Support, AKS documentation  
- **Google GKE**: Google Cloud Support, GKE documentation
- **DigitalOcean DOKS**: DigitalOcean Support, community forums

## Next Steps

1. Configure monitoring with cloud provider's monitoring service
2. Set up CI/CD pipelines for automated deployments
3. Configure backup solutions
4. Implement disaster recovery procedures
5. Set up multi-region deployments (if needed)

## Example Deployment Scenarios

### Scenario 1: AWS EKS with Auto-Scaling

```bash
# Create EKS cluster with managed node groups
eksctl create cluster \
  --name control-panel-prod \
  --region us-west-2 \
  --nodegroup-name workers \
  --node-type t3.large \
  --nodes 3 \
  --nodes-min 2 \
  --nodes-max 10 \
  --managed \
  --asg-access \
  --external-dns-access \
  --full-ecr-access

# Enable cluster autoscaler
kubectl apply -f https://raw.githubusercontent.com/kubernetes/autoscaler/master/cluster-autoscaler/cloudprovider/aws/examples/cluster-autoscaler-autodiscover.yaml

# Configure kubectl
aws eks update-kubeconfig --region us-west-2 --name control-panel-prod

# Install control panel
sudo ./install-k8s.sh
./install-control-panel.sh
```

### Scenario 2: Azure AKS with Azure AD Integration

```bash
# Create AKS with Azure AD
az aks create \
  --resource-group control-panel-rg \
  --name control-panel-prod \
  --node-count 3 \
  --enable-managed-identity \
  --enable-cluster-autoscaler \
  --min-count 2 \
  --max-count 10 \
  --enable-aad \
  --enable-azure-rbac \
  --enable-addons monitoring

# Get credentials
az aks get-credentials --resource-group control-panel-rg --name control-panel-prod

# Install control panel
sudo MANAGED_K8S=aks ./install-k8s.sh
./install-control-panel.sh
```

### Scenario 3: GKE with Autopilot

```bash
# Create Autopilot cluster (fully managed)
gcloud container clusters create-auto control-panel-prod \
  --region us-central1 \
  --release-channel regular

# Get credentials
gcloud container clusters get-credentials control-panel-prod --region us-central1

# Install control panel
sudo MANAGED_K8S=gke ./install-k8s.sh
./install-control-panel.sh
```

### Scenario 4: DigitalOcean DOKS with Block Storage

```bash
# Create DOKS cluster
doctl kubernetes cluster create control-panel-prod \
  --region nyc1 \
  --version 1.29.0-do.0 \
  --node-pool "name=workers;size=s-4vcpu-8gb;count=3;auto-scale=true;min-nodes=2;max-nodes=5"

# Get credentials (automatic)
doctl kubernetes cluster kubeconfig save control-panel-prod

# Install control panel
sudo MANAGED_K8S=doks ./install-k8s.sh
./install-control-panel.sh
```

## Node Joining for Self-Managed Clusters

If you're running a self-managed cluster and need to add worker nodes, use the simplified join script:

```bash
# On the master node, get the join command
kubeadm token create --print-join-command

# On the worker node, use the join script
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel
sudo ./join-node.sh

# The script will:
# 1. Install containerd and Kubernetes components
# 2. Prompt for the join command
# 3. Validate and join the cluster
# 4. Verify the node is added
```

## References

- [AWS EKS Documentation](https://docs.aws.amazon.com/eks/)
- [Azure AKS Documentation](https://docs.microsoft.com/en-us/azure/aks/)
- [Google GKE Documentation](https://cloud.google.com/kubernetes-engine/docs)
- [DigitalOcean DOKS Documentation](https://docs.digitalocean.com/products/kubernetes/)
- [Kubernetes Documentation](https://kubernetes.io/docs/)
