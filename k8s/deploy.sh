#!/bin/bash
# Kubernetes Deployment Script for Liberu Control Panel
# This script automates the deployment process

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE="${NAMESPACE:-control-panel}"
ENVIRONMENT="${ENVIRONMENT:-production}"
DOMAIN="${DOMAIN:-control-panel.example.com}"
APP_KEY="${APP_KEY:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"

echo -e "${GREEN}=== Liberu Control Panel Kubernetes Deployment ===${NC}"
echo ""

# Function to print colored messages
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
print_info "Checking prerequisites..."

if ! command -v kubectl &> /dev/null; then
    print_error "kubectl is not installed. Please install it first."
    exit 1
fi

if ! command -v kustomize &> /dev/null; then
    print_warn "kustomize is not installed. Trying to use kubectl kustomize instead..."
fi

# Validate required variables
if [ -z "$APP_KEY" ]; then
    print_error "APP_KEY is required. Generate one with: php artisan key:generate --show"
    exit 1
fi

if [ -z "$DB_PASSWORD" ]; then
    print_error "DB_PASSWORD is required."
    exit 1
fi

if [ -z "$DB_ROOT_PASSWORD" ]; then
    print_error "DB_ROOT_PASSWORD is required."
    exit 1
fi

# Create namespace
print_info "Creating namespace: $NAMESPACE"
kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f -

# Update secrets
print_info "Updating secrets..."
kubectl create secret generic control-panel-secrets \
    --from-literal=APP_KEY="$APP_KEY" \
    --from-literal=DB_USERNAME="controlpanel" \
    --from-literal=DB_PASSWORD="$DB_PASSWORD" \
    --from-literal=DB_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
    --from-literal=REDIS_PASSWORD="" \
    --namespace="$NAMESPACE" \
    --dry-run=client -o yaml | kubectl apply -f -

# Update ingress domain
print_info "Configuring ingress for domain: $DOMAIN"
if [ -f "k8s/overlays/$ENVIRONMENT/ingress-patch.yaml" ]; then
    rm "k8s/overlays/$ENVIRONMENT/ingress-patch.yaml"
fi

cat > "k8s/overlays/$ENVIRONMENT/ingress-patch.yaml" <<EOF
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: control-panel
spec:
  tls:
  - hosts:
    - $DOMAIN
    secretName: control-panel-tls
  rules:
  - host: $DOMAIN
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: control-panel
            port:
              number: 80
EOF

# Deploy using kustomize
print_info "Deploying to Kubernetes..."
if command -v kustomize &> /dev/null; then
    kustomize build "k8s/overlays/$ENVIRONMENT" | kubectl apply -f -
else
    kubectl apply -k "k8s/overlays/$ENVIRONMENT"
fi

# Wait for deployment
print_info "Waiting for deployment to be ready..."
kubectl wait --for=condition=available --timeout=300s \
    deployment/control-panel -n "$NAMESPACE" || true

# Run migrations
print_info "Running database migrations..."
POD=$(kubectl get pods -n "$NAMESPACE" -l app=control-panel,component=application -o jsonpath='{.items[0].metadata.name}')
if [ -n "$POD" ]; then
    kubectl exec -n "$NAMESPACE" "$POD" -c php-fpm -- php artisan migrate --force
    print_info "Migrations completed successfully"
else
    print_warn "Could not find pod to run migrations. Please run manually."
fi

# Display status
echo ""
print_info "Deployment completed!"
echo ""
echo "To check the status:"
echo "  kubectl get pods -n $NAMESPACE"
echo ""
echo "To view logs:"
echo "  kubectl logs -n $NAMESPACE -l app=control-panel -c php-fpm"
echo ""
echo "To access the application:"
echo "  https://$DOMAIN"
echo ""
