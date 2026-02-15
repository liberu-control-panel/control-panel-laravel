#!/bin/bash

################################################################################
# Control Panel Full Installation Script
# 
# This script installs the complete control panel stack on Kubernetes:
# - Control Panel (Laravel application with Octane)
# - MariaDB cluster with multi-user support
# - Redis for caching
# - NGINX Ingress with virtual hosts
# - PHP-FPM multi-version support (8.1-8.5)
# - Postfix for email sending
# - Dovecot for IMAP/POP3
# - DNS cluster (CoreDNS + PowerDNS)
# - Queue workers
################################################################################

set -euo pipefail

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
NAMESPACE="${NAMESPACE:-control-panel}"
DOMAIN="${DOMAIN:-control.example.com}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-admin@example.com}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-$(openssl rand -base64 32)}"
DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 32)}"
APP_KEY="${APP_KEY:-}"

# S3 Storage Configuration (for persistent volumes)
S3_ENABLED="${S3_ENABLED:-}"
S3_ENDPOINT="${S3_ENDPOINT:-}"
S3_ACCESS_KEY="${S3_ACCESS_KEY:-}"
S3_SECRET_KEY="${S3_SECRET_KEY:-}"
S3_BUCKET="${S3_BUCKET:-}"
S3_REGION="${S3_REGION:-us-east-1}"
STORAGE_CLASS="${STORAGE_CLASS:-standard}"

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

# Prompt for S3 storage configuration
prompt_s3_configuration() {
    log_info "Storage Configuration"
    echo ""
    echo "Kubernetes persistent volumes can use S3-compatible storage (e.g., MinIO, AWS S3, DigitalOcean Spaces)"
    echo "for better scalability and data persistence across cluster nodes."
    echo ""
    read -p "Do you want to configure S3-compatible storage for persistent volumes? (y/n) " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        S3_ENABLED="true"
        
        echo ""
        read -p "Enter S3 endpoint URL (e.g., https://s3.amazonaws.com or https://minio.example.com): " S3_ENDPOINT
        read -p "Enter S3 access key: " S3_ACCESS_KEY
        read -s -p "Enter S3 secret key: " S3_SECRET_KEY
        echo
        read -p "Enter S3 bucket name for persistent volumes: " S3_BUCKET
        read -p "Enter S3 region (default: us-east-1): " S3_REGION_INPUT
        S3_REGION="${S3_REGION_INPUT:-us-east-1}"
        
        log_success "S3 storage configuration saved"
    else
        S3_ENABLED="false"
        log_info "Using default Kubernetes storage class for persistent volumes"
    fi
    echo ""
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        log_error "kubectl not found. Please install Kubernetes first."
        exit 1
    fi
    
    # Check helm
    if ! command -v helm &> /dev/null; then
        log_info "Helm not found. Installing Helm..."
        curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
    fi
    
    # Check cluster access
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot access Kubernetes cluster. Please configure kubectl."
        exit 1
    fi
    
    log_success "Prerequisites check passed"
}

# Create namespace
create_namespace() {
    log_info "Creating namespace: $NAMESPACE"
    
    kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -
    
    log_success "Namespace created/verified"
}

# Add Helm repositories
add_helm_repos() {
    log_info "Adding Helm repositories..."
    
    helm repo add bitnami https://charts.bitnami.com/bitnami
    helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
    helm repo update
    
    log_success "Helm repositories added"
}

# Install MariaDB cluster
install_mariadb() {
    log_info "Installing MariaDB cluster..."
    
    helm upgrade --install mariadb bitnami/mariadb \
        --namespace $NAMESPACE \
        --set auth.rootPassword="$DB_ROOT_PASSWORD" \
        --set auth.database=controlpanel \
        --set auth.username=controlpanel \
        --set auth.password="$DB_PASSWORD" \
        --set primary.persistence.enabled=true \
        --set primary.persistence.size=20Gi \
        --set architecture=replication \
        --set secondary.replicaCount=2 \
        --set metrics.enabled=true \
        --wait
    
    log_success "MariaDB cluster installed"
    log_info "Root password: $DB_ROOT_PASSWORD"
    log_info "Database password: $DB_PASSWORD"
}

# Install Redis
install_redis() {
    log_info "Installing Redis..."
    
    helm upgrade --install redis bitnami/redis \
        --namespace $NAMESPACE \
        --set auth.enabled=false \
        --set master.persistence.enabled=false \
        --set replica.replicaCount=2 \
        --set metrics.enabled=true \
        --wait
    
    log_success "Redis installed"
}

# Generate APP_KEY if not provided
generate_app_key() {
    if [[ -z "$APP_KEY" ]]; then
        log_info "Generating Laravel APP_KEY..."
        # We'll generate it after deploying the app
        APP_KEY="base64:$(openssl rand -base64 32)"
        log_info "Generated APP_KEY: $APP_KEY"
    fi
}

# Install control panel
install_control_panel() {
    log_info "Installing Control Panel..."
    
    generate_app_key
    
    # Check if we're in the repository directory
    if [[ ! -f "helm/control-panel/Chart.yaml" ]]; then
        log_error "Control panel Helm chart not found. Please run from repository root."
        exit 1
    fi
    
    # Build helm install command with base parameters
    HELM_CMD="helm upgrade --install control-panel ./helm/control-panel \
        --namespace $NAMESPACE \
        --set app.key=\"$APP_KEY\" \
        --set app.url=\"https://$DOMAIN\" \
        --set ingress.hosts[0].host=\"$DOMAIN\" \
        --set ingress.tls[0].hosts[0]=\"$DOMAIN\" \
        --set ingress.tls[0].secretName=control-panel-tls \
        --set mysql.enabled=false \
        --set redis.enabled=false \
        --set replicaCount=3"
    
    # Add S3 configuration if enabled
    if [[ "$S3_ENABLED" == "true" ]]; then
        HELM_CMD="$HELM_CMD \
            --set s3.enabled=true \
            --set s3.endpoint=\"$S3_ENDPOINT\" \
            --set s3.accessKey=\"$S3_ACCESS_KEY\" \
            --set s3.secretKey=\"$S3_SECRET_KEY\" \
            --set s3.bucket=\"$S3_BUCKET\" \
            --set s3.region=\"$S3_REGION\" \
            --set persistence.storageClass=\"s3-storage\""
    fi
    
    HELM_CMD="$HELM_CMD --wait"
    
    # Execute the helm command
    eval $HELM_CMD
    
    log_success "Control Panel installed"
}

# Install mail services (Postfix + Dovecot)
install_mail_services() {
    log_info "Installing mail services..."
    
    # Deploy mail services chart
    if [[ -d "helm/mail-services" ]]; then
        helm upgrade --install mail-services ./helm/mail-services \
            --namespace $NAMESPACE \
            --set domain="$DOMAIN" \
            --wait
        log_success "Mail services installed"
    else
        log_warning "Mail services chart not found. Skipping..."
    fi
}

# Install DNS cluster
install_dns_cluster() {
    log_info "Installing DNS cluster..."
    
    # Deploy DNS services chart
    if [[ -d "helm/dns-cluster" ]]; then
        helm upgrade --install dns-cluster ./helm/dns-cluster \
            --namespace $NAMESPACE \
            --wait
        log_success "DNS cluster installed"
    else
        log_warning "DNS cluster chart not found. Skipping..."
    fi
}

# Install PHP multi-version support
install_php_multiversion() {
    log_info "Installing PHP multi-version support..."
    
    # Deploy PHP versions chart
    if [[ -d "helm/php-versions" ]]; then
        helm upgrade --install php-versions ./helm/php-versions \
            --namespace $NAMESPACE \
            --wait
        log_success "PHP multi-version support installed"
    else
        log_warning "PHP versions chart not found. Skipping..."
    fi
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."
    
    # Wait for pods to be ready
    kubectl wait --for=condition=ready pod \
        -l app.kubernetes.io/name=control-panel \
        -n $NAMESPACE \
        --timeout=300s
    
    # Get pod name
    POD=$(kubectl get pods -n $NAMESPACE -l app.kubernetes.io/name=control-panel -o jsonpath='{.items[0].metadata.name}')
    
    # Run migrations
    kubectl exec -n $NAMESPACE $POD -c php-fpm -- php artisan migrate --force
    
    log_success "Database migrations completed"
}

# Create admin user
create_admin_user() {
    log_info "Creating admin user..."
    
    POD=$(kubectl get pods -n $NAMESPACE -l app.kubernetes.io/name=control-panel -o jsonpath='{.items[0].metadata.name}')
    
    echo ""
    echo "Please provide admin user details:"
    kubectl exec -it -n $NAMESPACE $POD -c php-fpm -- php artisan make:filament-user
    
    log_success "Admin user created"
}

# Display access information
display_access_info() {
    echo ""
    echo "========================================="
    echo "INSTALLATION COMPLETE!"
    echo "========================================="
    echo ""
    echo "Control Panel URL: https://$DOMAIN"
    echo ""
    echo "Database Information:"
    echo "  Host: mariadb.$NAMESPACE.svc.cluster.local"
    echo "  Database: controlpanel"
    echo "  Username: controlpanel"
    echo "  Password: $DB_PASSWORD"
    echo "  Root Password: $DB_ROOT_PASSWORD"
    echo ""
    echo "Redis:"
    echo "  Host: redis-master.$NAMESPACE.svc.cluster.local"
    echo ""
    
    if [[ "$S3_ENABLED" == "true" ]]; then
        echo "S3 Storage:"
        echo "  Endpoint: $S3_ENDPOINT"
        echo "  Bucket: $S3_BUCKET"
        echo "  Region: $S3_REGION"
        echo ""
    fi
    
    echo "IMPORTANT: Save these credentials securely!"
    echo ""
    echo "To access the application:"
    echo "1. Configure DNS to point $DOMAIN to your Ingress IP"
    echo "2. Wait for Let's Encrypt certificate to be issued (may take a few minutes)"
    echo "3. Access https://$DOMAIN in your browser"
    echo ""
}

# Save configuration
save_configuration() {
    log_info "Saving configuration..."
    
    cat > /tmp/control-panel-config.txt <<EOF
Control Panel Installation Configuration
========================================

Namespace: $NAMESPACE
Domain: $DOMAIN
Let's Encrypt Email: $LETSENCRYPT_EMAIL

Database:
  Root Password: $DB_ROOT_PASSWORD
  Database: controlpanel
  Username: controlpanel
  Password: $DB_PASSWORD

Application:
  APP_KEY: $APP_KEY

EOF

    if [[ "$S3_ENABLED" == "true" ]]; then
        cat >> /tmp/control-panel-config.txt <<EOF
S3 Storage:
  Endpoint: $S3_ENDPOINT
  Access Key: $S3_ACCESS_KEY
  Secret Key: $S3_SECRET_KEY
  Bucket: $S3_BUCKET
  Region: $S3_REGION

EOF
    fi

    echo "Installation Date: $(date)" >> /tmp/control-panel-config.txt
    
    log_success "Configuration saved to /tmp/control-panel-config.txt"
}

# Main installation flow
main() {
    echo ""
    log_info "Starting Control Panel installation..."
    echo ""
    
    check_prerequisites
    prompt_s3_configuration
    create_namespace
    add_helm_repos
    
    install_mariadb
    install_redis
    install_control_panel
    install_mail_services
    install_dns_cluster
    install_php_multiversion
    
    run_migrations
    
    # Optionally create admin user
    read -p "Do you want to create an admin user now? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        create_admin_user
    fi
    
    save_configuration
    display_access_info
    
    log_success "Installation completed successfully!"
}

# Run main function
main "$@"
