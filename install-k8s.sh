#!/bin/bash

################################################################################
# Kubernetes Installation Script for Control Panel
# Supports: 
# - Self-managed: Ubuntu LTS (20.04, 22.04, 24.04) and AlmaLinux/RHEL 8/9
# - Managed: AWS EKS, Azure AKS, Google GKE, DigitalOcean DOKS
# 
# This script:
# - Detects if running on managed Kubernetes (EKS, AKS, GKE, DOKS)
# - For self-managed: Installs complete cluster with kubeadm
# - For managed: Configures kubectl and installs required add-ons only
# - Installs essential add-ons (NGINX Ingress, cert-manager, etc.)
################################################################################

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
K8S_VERSION="${K8S_VERSION:-1.29}"
POD_NETWORK_CIDR="${POD_NETWORK_CIDR:-10.244.0.0/16}"
SERVICE_CIDR="${SERVICE_CIDR:-10.96.0.0/12}"
CONTROL_PANEL_DOMAIN="${CONTROL_PANEL_DOMAIN:-control.example.com}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-admin@example.com}"
MANAGED_K8S="${MANAGED_K8S:-auto}"  # auto, eks, aks, gke, doks, or none

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
        exit 1
    fi
}

# Detect OS
detect_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
        log_info "Detected OS: $OS $OS_VERSION"
    else
        log_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi
}

# Detect if running on managed Kubernetes
detect_managed_k8s() {
    if [[ "$MANAGED_K8S" != "auto" ]]; then
        log_info "Managed Kubernetes mode set via environment: $MANAGED_K8S"
        return
    fi
    
    log_info "Detecting managed Kubernetes environment..."
    
    # Check for AWS EKS
    if curl -s --max-time 2 http://169.254.169.254/latest/meta-data/instance-id &>/dev/null; then
        if systemctl list-unit-files | grep -q amazon-ssm-agent; then
            MANAGED_K8S="eks"
            log_info "Detected AWS EKS environment"
            return
        fi
    fi
    
    # Check for Azure AKS
    if curl -s -H Metadata:true --max-time 2 "http://169.254.169.254/metadata/instance?api-version=2021-02-01" | grep -q "azure" &>/dev/null; then
        MANAGED_K8S="aks"
        log_info "Detected Azure AKS environment"
        return
    fi
    
    # Check for Google GKE
    if curl -s -H "Metadata-Flavor: Google" --max-time 2 http://metadata.google.internal/computeMetadata/v1/instance/attributes/cluster-name &>/dev/null; then
        MANAGED_K8S="gke"
        log_info "Detected Google GKE environment"
        return
    fi
    
    # Check for DigitalOcean DOKS
    if curl -s --max-time 2 http://169.254.169.254/metadata/v1/region | grep -q "digitalocean" &>/dev/null; then
        MANAGED_K8S="doks"
        log_info "Detected DigitalOcean DOKS environment"
        return
    fi
    
    # Check if kubectl is already configured (manual managed setup)
    if command -v kubectl &>/dev/null && kubectl cluster-info &>/dev/null; then
        log_info "kubectl is already configured with cluster access"
        echo ""
        read -p "Are you using a managed Kubernetes service (EKS/AKS/GKE/DOKS)? [y/N]: " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            echo "Select your managed Kubernetes provider:"
            echo "1) AWS EKS"
            echo "2) Azure AKS"
            echo "3) Google GKE"
            echo "4) DigitalOcean DOKS"
            read -p "Enter choice [1-4]: " managed_choice
            case $managed_choice in
                1) MANAGED_K8S="eks" ;;
                2) MANAGED_K8S="aks" ;;
                3) MANAGED_K8S="gke" ;;
                4) MANAGED_K8S="doks" ;;
                *) MANAGED_K8S="none" ;;
            esac
            log_info "Using managed Kubernetes: $MANAGED_K8S"
            return
        fi
    fi
    
    MANAGED_K8S="none"
    log_info "No managed Kubernetes detected - will install self-managed cluster"
}

# Skip to managed Kubernetes setup
setup_managed_k8s() {
    log_info "Setting up for managed Kubernetes ($MANAGED_K8S)..."
    echo ""
    log_warning "This script detected you're using a managed Kubernetes service."
    log_info "The control plane is already managed by your cloud provider."
    echo ""
    log_info "This script will:"
    echo "  1. Verify kubectl is properly configured"
    echo "  2. Install essential add-ons (NGINX Ingress, cert-manager, Metrics Server)"
    echo "  3. Skip kubeadm cluster initialization (not needed for managed K8s)"
    echo ""
    read -p "Continue with managed Kubernetes setup? [Y/n]: " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        log_info "Setup cancelled"
        exit 0
    fi
    
    # Verify kubectl access
    verify_kubectl_access
    
    # Install add-ons
    install_addons_managed
    
    # Display next steps
    display_managed_next_steps
}

# Verify kubectl access for managed K8s
verify_kubectl_access() {
    log_info "Verifying kubectl access to cluster..."
    
    if ! command -v kubectl &>/dev/null; then
        log_error "kubectl is not installed"
        echo ""
        log_info "Please install kubectl first. See:"
        log_info "  - EKS: https://docs.aws.amazon.com/eks/latest/userguide/install-kubectl.html"
        log_info "  - AKS: https://docs.microsoft.com/en-us/azure/aks/kubernetes-walkthrough"
        log_info "  - GKE: https://cloud.google.com/kubernetes-engine/docs/how-to/cluster-access-for-kubectl"
        log_info "  - DOKS: https://docs.digitalocean.com/products/kubernetes/how-to/connect-to-cluster/"
        exit 1
    fi
    
    if ! kubectl cluster-info &>/dev/null; then
        log_error "kubectl is not configured to access a cluster"
        echo ""
        log_info "Please configure kubectl first. See:"
        case $MANAGED_K8S in
            eks)
                log_info "  aws eks update-kubeconfig --region <region> --name <cluster-name>"
                log_info "  https://docs.aws.amazon.com/eks/latest/userguide/create-kubeconfig.html"
                ;;
            aks)
                log_info "  az aks get-credentials --resource-group <rg> --name <cluster-name>"
                log_info "  https://docs.microsoft.com/en-us/azure/aks/kubernetes-walkthrough"
                ;;
            gke)
                log_info "  gcloud container clusters get-credentials <cluster-name> --region <region>"
                log_info "  https://cloud.google.com/kubernetes-engine/docs/how-to/cluster-access-for-kubectl"
                ;;
            doks)
                log_info "  doctl kubernetes cluster kubeconfig save <cluster-name>"
                log_info "  https://docs.digitalocean.com/products/kubernetes/how-to/connect-to-cluster/"
                ;;
        esac
        exit 1
    fi
    
    log_success "kubectl is properly configured"
    log_info "Connected to cluster: $(kubectl config current-context)"
}

# Install add-ons for managed Kubernetes
install_addons_managed() {
    log_info "Installing essential add-ons for managed Kubernetes..."
    
    # Check if NGINX Ingress is needed
    case $MANAGED_K8S in
        eks)
            log_info "For EKS, you can use AWS Load Balancer Controller or NGINX Ingress"
            read -p "Install NGINX Ingress Controller? [Y/n]: " -n 1 -r
            echo ""
            if [[ ! $REPLY =~ ^[Nn]$ ]]; then
                install_nginx_ingress
            fi
            ;;
        aks|gke|doks)
            log_info "Installing NGINX Ingress Controller..."
            install_nginx_ingress
            ;;
    esac
    
    # Install cert-manager
    install_cert_manager
    
    # Install Metrics Server (if not already present)
    install_metrics_server_if_needed
}

# Install Metrics Server if not present
install_metrics_server_if_needed() {
    log_info "Checking for Metrics Server..."
    
    if kubectl get deployment metrics-server -n kube-system &>/dev/null; then
        log_success "Metrics Server is already installed"
    else
        log_info "Installing Metrics Server..."
        install_metrics_server
    fi
}

# Display next steps for managed Kubernetes
display_managed_next_steps() {
    echo ""
    echo "========================================="
    echo "MANAGED KUBERNETES SETUP COMPLETE"
    echo "========================================="
    echo ""
    log_success "Your managed Kubernetes cluster is ready for the control panel"
    echo ""
    echo "========================================="
    echo "NEXT STEPS"
    echo "========================================="
    echo ""
    echo "1. Get the Ingress external IP/hostname:"
    echo "   kubectl get service -n ingress-nginx ingress-nginx-controller"
    echo ""
    echo "2. Point your domain DNS to the Ingress IP/hostname"
    echo ""
    echo "3. Deploy the control panel:"
    echo "   ./install-control-panel.sh"
    echo ""
    echo "4. For detailed documentation on managed Kubernetes, see:"
    echo "   docs/MANAGED_KUBERNETES_SETUP.md"
    echo ""
}

# Check if node is master or worker
determine_node_type() {
    log_info "Determining node type..."
    
    if [[ -n "${NODE_TYPE:-}" ]]; then
        log_info "Node type set via environment: $NODE_TYPE"
        return
    fi
    
    echo ""
    echo "Is this node a Kubernetes master (control plane) or worker node?"
    echo "1) Master (control plane)"
    echo "2) Worker"
    read -p "Enter choice [1-2]: " choice
    
    case $choice in
        1)
            NODE_TYPE="master"
            log_info "Node will be configured as MASTER"
            ;;
        2)
            NODE_TYPE="worker"
            log_info "Node will be configured as WORKER"
            ;;
        *)
            log_error "Invalid choice"
            exit 1
            ;;
    esac
}

# Disable swap (required for Kubernetes)
disable_swap() {
    log_info "Disabling swap..."
    swapoff -a
    sed -i '/ swap / s/^/#/' /etc/fstab
    log_success "Swap disabled"
}

# Load kernel modules
load_kernel_modules() {
    log_info "Loading required kernel modules..."
    
    cat <<EOF | tee /etc/modules-load.d/k8s.conf
overlay
br_netfilter
EOF

    modprobe overlay
    modprobe br_netfilter
    
    log_success "Kernel modules loaded"
}

# Configure sysctl
configure_sysctl() {
    log_info "Configuring sysctl parameters..."
    
    cat <<EOF | tee /etc/sysctl.d/k8s.conf
net.bridge.bridge-nf-call-iptables  = 1
net.bridge.bridge-nf-call-ip6tables = 1
net.ipv4.ip_forward                 = 1
EOF

    sysctl --system
    log_success "Sysctl parameters configured"
}

# Install containerd for Ubuntu
install_containerd_ubuntu() {
    log_info "Installing containerd for Ubuntu..."
    
    apt-get update
    apt-get install -y ca-certificates curl gnupg lsb-release
    
    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    
    # Set up the repository
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    apt-get update
    apt-get install -y containerd.io
    
    configure_containerd
    log_success "Containerd installed"
}

# Install containerd for RHEL/AlmaLinux
install_containerd_rhel() {
    log_info "Installing containerd for RHEL/AlmaLinux..."
    
    dnf install -y dnf-utils
    dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    dnf install -y containerd.io
    
    configure_containerd
    log_success "Containerd installed"
}

# Configure containerd
configure_containerd() {
    log_info "Configuring containerd..."
    
    mkdir -p /etc/containerd
    containerd config default | tee /etc/containerd/config.toml
    
    # Enable SystemdCgroup
    sed -i 's/SystemdCgroup = false/SystemdCgroup = true/' /etc/containerd/config.toml
    
    systemctl restart containerd
    systemctl enable containerd
    
    log_success "Containerd configured"
}

# Install Kubernetes components for Ubuntu
install_kubernetes_ubuntu() {
    log_info "Installing Kubernetes components for Ubuntu..."
    
    apt-get update
    apt-get install -y apt-transport-https ca-certificates curl gpg
    
    # Add Kubernetes GPG key and repository
    curl -fsSL https://pkgs.k8s.io/core:/stable:/v${K8S_VERSION}/deb/Release.key | gpg --dearmor -o /etc/apt/keyrings/kubernetes-apt-keyring.gpg
    
    echo "deb [signed-by=/etc/apt/keyrings/kubernetes-apt-keyring.gpg] https://pkgs.k8s.io/core:/stable:/v${K8S_VERSION}/deb/ /" | tee /etc/apt/sources.list.d/kubernetes.list
    
    apt-get update
    apt-get install -y kubelet kubeadm kubectl
    apt-mark hold kubelet kubeadm kubectl
    
    systemctl enable kubelet
    
    log_success "Kubernetes components installed"
}

# Install Kubernetes components for RHEL/AlmaLinux
install_kubernetes_rhel() {
    log_info "Installing Kubernetes components for RHEL/AlmaLinux..."
    
    cat <<EOF | tee /etc/yum.repos.d/kubernetes.repo
[kubernetes]
name=Kubernetes
baseurl=https://pkgs.k8s.io/core:/stable:/v${K8S_VERSION}/rpm/
enabled=1
gpgcheck=1
gpgkey=https://pkgs.k8s.io/core:/stable:/v${K8S_VERSION}/rpm/repodata/repomd.xml.key
exclude=kubelet kubeadm kubectl cri-tools kubernetes-cni
EOF

    # Set SELinux in permissive mode
    setenforce 0
    sed -i 's/^SELINUX=enforcing$/SELINUX=permissive/' /etc/selinux/config
    
    dnf install -y kubelet kubeadm kubectl --disableexcludes=kubernetes
    systemctl enable kubelet
    
    log_success "Kubernetes components installed"
}

# Initialize Kubernetes master
initialize_master() {
    log_info "Initializing Kubernetes master node..."
    
    # Get the node's IP address
    NODE_IP=$(hostname -I | awk '{print $1}')
    
    kubeadm init \
        --pod-network-cidr=$POD_NETWORK_CIDR \
        --service-cidr=$SERVICE_CIDR \
        --apiserver-advertise-address=$NODE_IP \
        --kubernetes-version=$(kubeadm version -o short)
    
    # Set up kubeconfig for root
    mkdir -p $HOME/.kube
    cp -i /etc/kubernetes/admin.conf $HOME/.kube/config
    chown $(id -u):$(id -g) $HOME/.kube/config
    
    log_success "Kubernetes master initialized"
    
    # Install CNI plugin (Calico)
    install_cni
    
    # Install essential add-ons
    install_addons
    
    # Display join command
    display_join_command
}

# Install CNI plugin (Calico)
install_cni() {
    log_info "Installing Calico CNI plugin..."
    
    kubectl create -f https://raw.githubusercontent.com/projectcalico/calico/v3.27.0/manifests/tigera-operator.yaml
    
    cat <<EOF | kubectl apply -f -
apiVersion: operator.tigera.io/v1
kind: Installation
metadata:
  name: default
spec:
  calicoNetwork:
    ipPools:
    - blockSize: 26
      cidr: $POD_NETWORK_CIDR
      encapsulation: VXLANCrossSubnet
      natOutgoing: Enabled
      nodeSelector: all()
EOF

    log_success "Calico CNI plugin installed"
}

# Install essential add-ons
install_addons() {
    log_info "Installing essential add-ons..."
    
    # Install NGINX Ingress Controller
    install_nginx_ingress
    
    # Install cert-manager for Let's Encrypt
    install_cert_manager
    
    # Install Metrics Server
    install_metrics_server
}

# Install NGINX Ingress Controller
install_nginx_ingress() {
    log_info "Installing NGINX Ingress Controller..."
    
    kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.10.0/deploy/static/provider/cloud/deploy.yaml
    
    log_success "NGINX Ingress Controller installed"
}

# Install cert-manager
install_cert_manager() {
    log_info "Installing cert-manager..."
    
    kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.14.2/cert-manager.yaml
    
    # Wait for cert-manager to be ready
    sleep 30
    
    # Create ClusterIssuers for Let's Encrypt
    cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: $LETSENCRYPT_EMAIL
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
    email: $LETSENCRYPT_EMAIL
    privateKeySecretRef:
      name: letsencrypt-staging
    solvers:
    - http01:
        ingress:
          class: nginx
EOF

    log_success "cert-manager installed with Let's Encrypt issuers"
}

# Install Metrics Server
install_metrics_server() {
    log_info "Installing Metrics Server..."
    
    kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
    
    log_success "Metrics Server installed"
}

# Display join command for worker nodes
display_join_command() {
    log_info "Generating join command for worker nodes..."
    
    echo ""
    echo "========================================="
    echo "KUBERNETES CLUSTER INITIALIZED"
    echo "========================================="
    echo ""
    echo "To join worker nodes to this cluster, run the following command on each worker:"
    echo ""
    kubeadm token create --print-join-command
    echo ""
    echo "Save this command securely. You'll need it to add worker nodes."
    echo ""
    echo "========================================="
    echo "NEXT STEPS"
    echo "========================================="
    echo ""
    echo "1. Deploy the control panel:"
    echo "   ./install-control-panel.sh"
    echo ""
    echo "2. During installation, you can configure S3-compatible storage"
    echo "   for persistent volumes (AWS S3, MinIO, DigitalOcean Spaces, etc.)"
    echo ""
    echo "3. For detailed S3 storage configuration, see:"
    echo "   docs/S3_STORAGE.md"
    echo ""
}

# Join worker node to cluster
join_worker() {
    log_info "Preparing to join worker node to cluster..."
    
    if [[ -z "${JOIN_COMMAND:-}" ]]; then
        echo ""
        read -p "Enter the complete 'kubeadm join' command from the master node: " JOIN_COMMAND
    fi
    
    log_info "Joining cluster..."
    eval $JOIN_COMMAND
    
    log_success "Worker node joined to cluster"
}

# Main installation flow
main() {
    log_info "Starting Kubernetes installation..."
    echo ""
    
    check_root
    detect_os
    detect_managed_k8s
    
    # If managed Kubernetes detected, use simplified setup
    if [[ "$MANAGED_K8S" != "none" ]]; then
        setup_managed_k8s
        exit 0
    fi
    
    # Self-managed cluster setup
    determine_node_type
    
    # Common setup for all nodes
    disable_swap
    load_kernel_modules
    configure_sysctl
    
    # Install container runtime
    case $OS in
        ubuntu)
            install_containerd_ubuntu
            install_kubernetes_ubuntu
            ;;
        almalinux|rhel|rocky)
            install_containerd_rhel
            install_kubernetes_rhel
            ;;
        *)
            log_error "Unsupported OS: $OS"
            exit 1
            ;;
    esac
    
    # Node-specific setup
    if [[ "$NODE_TYPE" == "master" ]]; then
        initialize_master
        
        log_success "Kubernetes master installation complete!"
        log_info "You can now deploy the control panel using: ./install-control-panel.sh"
    else
        join_worker
        log_success "Worker node installation complete!"
    fi
    
    echo ""
    log_success "Installation completed successfully!"
}

# Run main function
main "$@"
