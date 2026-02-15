#!/bin/bash

################################################################################
# Kubernetes Worker Node Join Script
# 
# This script simplifies joining worker nodes to an existing Kubernetes cluster
# Supports: Ubuntu LTS (20.04, 22.04, 24.04), Debian (11, 12), and AlmaLinux/RHEL 8/9
#
# Usage:
#   1. Get the join command from your master node:
#      kubeadm token create --print-join-command
#   2. Run this script on the worker node with the join command:
#      sudo JOIN_COMMAND="kubeadm join ..." ./join-node.sh
#   
#   Or run interactively without parameters and provide the join command when prompted
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

# Print banner
print_banner() {
    echo ""
    echo "========================================="
    echo "  Kubernetes Worker Node Join Script"
    echo "========================================="
    echo ""
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

# Check if node is already part of a cluster
check_existing_cluster() {
    if systemctl is-active --quiet kubelet; then
        log_warning "kubelet is already running on this node"
        if kubectl get nodes &>/dev/null; then
            log_error "This node appears to be already joined to a cluster"
            read -p "Do you want to reset and rejoin? [y/N]: " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                log_info "Resetting node..."
                kubeadm reset -f
                log_success "Node reset complete"
            else
                log_info "Exiting without changes"
                exit 0
            fi
        fi
    fi
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
    log_info "Installing containerd for Ubuntu/Debian..."
    
    apt-get update
    apt-get install -y ca-certificates curl gnupg lsb-release
    
    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    
    if [[ "$OS" == "ubuntu" ]]; then
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
        chmod a+r /etc/apt/keyrings/docker.gpg
        
        # Set up the repository
        echo \
          "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
          $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    elif [[ "$OS" == "debian" ]]; then
        curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
        chmod a+r /etc/apt/keyrings/docker.gpg
        
        # Set up the repository
        echo \
          "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
          $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    fi
    
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

# Install Kubernetes components for Ubuntu/Debian
install_kubernetes_ubuntu() {
    log_info "Installing Kubernetes components for Ubuntu/Debian..."
    
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

# Get join command from user
get_join_command() {
    if [[ -z "${JOIN_COMMAND:-}" ]]; then
        echo ""
        log_info "You need a join command from your Kubernetes master node"
        log_info "To get it, run this on the master node:"
        echo ""
        echo "  kubeadm token create --print-join-command"
        echo ""
        log_info "Then paste the complete output here:"
        echo ""
        read -p "Join command: " JOIN_COMMAND
        echo ""
        
        if [[ -z "$JOIN_COMMAND" ]]; then
            log_error "Join command cannot be empty"
            exit 1
        fi
        
        # Validate join command format
        if [[ ! "$JOIN_COMMAND" =~ ^kubeadm\ join ]]; then
            log_error "Invalid join command format. Should start with 'kubeadm join'"
            exit 1
        fi
    fi
}

# Validate join command
validate_join_command() {
    log_info "Validating join command format..."
    
    # Check if command contains required parts
    if [[ ! "$JOIN_COMMAND" =~ --token ]] || [[ ! "$JOIN_COMMAND" =~ --discovery-token-ca-cert-hash ]]; then
        log_error "Join command is missing required parameters (--token or --discovery-token-ca-cert-hash)"
        log_info "Expected format: kubeadm join <master-ip>:6443 --token <token> --discovery-token-ca-cert-hash sha256:<hash>"
        exit 1
    fi
    
    log_success "Join command format validated"
}

# Join the cluster
join_cluster() {
    log_info "Joining the Kubernetes cluster..."
    echo ""
    
    # Execute join command
    eval $JOIN_COMMAND
    
    log_success "Successfully joined the cluster!"
}

# Display success message
display_success() {
    echo ""
    echo "========================================="
    echo "  Worker Node Joined Successfully!"
    echo "========================================="
    echo ""
    log_success "This node has been added to the Kubernetes cluster"
    echo ""
    log_info "To verify the node was added, run this on the master node:"
    echo ""
    echo "  kubectl get nodes"
    echo ""
    log_info "The new node should appear in the list (may take a minute to be Ready)"
    echo ""
}

# Main installation flow
main() {
    print_banner
    
    check_root
    detect_os
    check_existing_cluster
    
    log_info "This script will:"
    echo "  1. Install container runtime (containerd)"
    echo "  2. Install Kubernetes components (kubelet, kubeadm, kubectl)"
    echo "  3. Join this node to your Kubernetes cluster"
    echo ""
    read -p "Do you want to continue? [Y/n]: " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        log_info "Installation cancelled"
        exit 0
    fi
    
    # Common setup
    disable_swap
    load_kernel_modules
    configure_sysctl
    
    # Install container runtime and Kubernetes based on OS
    case $OS in
        ubuntu|debian)
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
    
    # Get and validate join command
    get_join_command
    validate_join_command
    
    # Join the cluster
    join_cluster
    
    # Display success message
    display_success
}

# Run main function
main "$@"
