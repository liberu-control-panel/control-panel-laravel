#!/bin/bash

################################################################################
# Kubernetes Deployment Validation Script
# 
# This script validates Kubernetes manifests and checks the deployment status
################################################################################

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
K8S_DIR="${SCRIPT_DIR}"

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

# Check if required tools are installed
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    local missing_tools=()
    
    if ! command -v kubectl &> /dev/null; then
        missing_tools+=("kubectl")
    fi
    
    if ! command -v kustomize &> /dev/null; then
        log_warning "kustomize not found, will use kubectl kustomize instead"
    fi
    
    if [ ${#missing_tools[@]} -gt 0 ]; then
        log_error "Missing required tools: ${missing_tools[*]}"
        return 1
    fi
    
    log_success "All prerequisites met"
}

# Validate YAML syntax
validate_yaml_syntax() {
    log_info "Validating YAML syntax..."
    
    local errors=0
    
    for file in "$K8S_DIR"/base/*.yaml; do
        if [ -f "$file" ]; then
            if kubectl apply --dry-run=client -f "$file" &> /dev/null; then
                log_success "✓ $(basename "$file")"
            else
                log_error "✗ $(basename "$file") - Invalid YAML"
                errors=$((errors + 1))
            fi
        fi
    done
    
    if [ $errors -eq 0 ]; then
        log_success "All YAML files are valid"
        return 0
    else
        log_error "$errors YAML file(s) failed validation"
        return 1
    fi
}

# Validate Kustomize builds
validate_kustomize() {
    log_info "Validating Kustomize builds..."
    
    local environments=("development" "production")
    local errors=0
    
    for env in "${environments[@]}"; do
        local overlay_dir="$K8S_DIR/overlays/$env"
        
        if [ -d "$overlay_dir" ]; then
            log_info "Validating $env environment..."
            
            if kubectl kustomize "$overlay_dir" > /dev/null 2>&1; then
                log_success "✓ $env overlay builds successfully"
            else
                log_error "✗ $env overlay failed to build"
                errors=$((errors + 1))
            fi
        else
            log_warning "Overlay directory not found: $overlay_dir"
        fi
    done
    
    if [ $errors -eq 0 ]; then
        log_success "All Kustomize overlays are valid"
        return 0
    else
        log_error "$errors overlay(s) failed validation"
        return 1
    fi
}

# Check cluster connectivity
check_cluster_connectivity() {
    log_info "Checking cluster connectivity..."
    
    if kubectl cluster-info &> /dev/null; then
        log_success "Connected to Kubernetes cluster"
        kubectl cluster-info | head -n 1
        return 0
    else
        log_error "Cannot connect to Kubernetes cluster"
        log_info "Make sure you have a valid kubeconfig and the cluster is accessible"
        return 1
    fi
}

# Check namespace
check_namespace() {
    local namespace="${1:-control-panel}"
    
    log_info "Checking namespace: $namespace..."
    
    if kubectl get namespace "$namespace" &> /dev/null; then
        log_success "Namespace '$namespace' exists"
        return 0
    else
        log_warning "Namespace '$namespace' does not exist"
        read -p "Create namespace? (y/N) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            if kubectl create namespace "$namespace"; then
                log_success "Namespace created"
                return 0
            else
                log_error "Failed to create namespace"
                return 1
            fi
        else
            return 1
        fi
    fi
}

# Validate deployment
validate_deployment() {
    local namespace="${1:-control-panel}"
    
    log_info "Checking deployment status in namespace: $namespace..."
    
    if ! kubectl get namespace "$namespace" &> /dev/null; then
        log_error "Namespace '$namespace' does not exist"
        return 1
    fi
    
    # Check if deployment exists
    if kubectl get deployment -n "$namespace" control-panel &> /dev/null; then
        local replicas=$(kubectl get deployment -n "$namespace" control-panel -o jsonpath='{.status.replicas}')
        local ready=$(kubectl get deployment -n "$namespace" control-panel -o jsonpath='{.status.readyReplicas}')
        local available=$(kubectl get deployment -n "$namespace" control-panel -o jsonpath='{.status.availableReplicas}')
        
        log_info "Deployment status:"
        echo "  Replicas: $replicas"
        echo "  Ready: ${ready:-0}"
        echo "  Available: ${available:-0}"
        
        if [ "${ready:-0}" -eq "$replicas" ] && [ "${available:-0}" -eq "$replicas" ]; then
            log_success "Deployment is healthy"
            return 0
        else
            log_warning "Deployment is not fully ready"
            
            # Show pod status
            log_info "Pod status:"
            kubectl get pods -n "$namespace" -l app=control-panel
            
            # Show recent events
            log_info "Recent events:"
            kubectl get events -n "$namespace" --sort-by='.lastTimestamp' | tail -10
            
            return 1
        fi
    else
        log_warning "Deployment 'control-panel' not found in namespace '$namespace'"
        return 1
    fi
}

# Check services
check_services() {
    local namespace="${1:-control-panel}"
    
    log_info "Checking services in namespace: $namespace..."
    
    if kubectl get service -n "$namespace" &> /dev/null; then
        kubectl get service -n "$namespace"
        log_success "Services listed"
        return 0
    else
        log_warning "No services found or namespace does not exist"
        return 1
    fi
}

# Check ingress
check_ingress() {
    local namespace="${1:-control-panel}"
    
    log_info "Checking ingress in namespace: $namespace..."
    
    if kubectl get ingress -n "$namespace" &> /dev/null 2>&1; then
        kubectl get ingress -n "$namespace"
        log_success "Ingress listed"
        return 0
    else
        log_warning "No ingress found or ingress controller not installed"
        return 0
    fi
}

# Run health checks
check_health() {
    local namespace="${1:-control-panel}"
    
    log_info "Running health checks..."
    
    # Get service endpoint
    local service_ip=$(kubectl get service -n "$namespace" control-panel -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "")
    
    if [ -z "$service_ip" ]; then
        # Try with NodePort
        service_ip=$(kubectl get nodes -o jsonpath='{.items[0].status.addresses[?(@.type=="ExternalIP")].address}' 2>/dev/null || echo "")
    fi
    
    if [ -n "$service_ip" ]; then
        log_info "Testing health endpoint: http://$service_ip/health"
        
        if curl -f -s "http://$service_ip/health" > /dev/null 2>&1; then
            log_success "Health check passed"
        else
            log_warning "Health check failed or endpoint not accessible"
        fi
    else
        log_warning "Could not determine service IP for health check"
    fi
}

# Main validation function
main() {
    echo ""
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║  Kubernetes Deployment Validation                        ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo ""
    
    local namespace="${1:-control-panel}"
    local skip_cluster_checks="${SKIP_CLUSTER_CHECKS:-false}"
    
    # Run validations
    check_prerequisites || exit 1
    
    echo ""
    validate_yaml_syntax || {
        log_error "YAML validation failed"
        exit 1
    }
    
    echo ""
    validate_kustomize || {
        log_error "Kustomize validation failed"
        exit 1
    }
    
    if [ "$skip_cluster_checks" = "false" ]; then
        echo ""
        check_cluster_connectivity || {
            log_warning "Cluster connectivity check failed, skipping deployment checks"
            log_info "Run with SKIP_CLUSTER_CHECKS=true to skip cluster checks"
            exit 0
        }
        
        echo ""
        check_namespace "$namespace"
        
        echo ""
        validate_deployment "$namespace"
        
        echo ""
        check_services "$namespace"
        
        echo ""
        check_ingress "$namespace"
        
        echo ""
        check_health "$namespace"
    else
        log_info "Skipping cluster checks (SKIP_CLUSTER_CHECKS=true)"
    fi
    
    echo ""
    log_success "╔═══════════════════════════════════════════════════════════╗"
    log_success "║  Validation Complete                                     ║"
    log_success "╚═══════════════════════════════════════════════════════════╝"
    echo ""
}

# Run main function
main "$@"
