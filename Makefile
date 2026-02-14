# Makefile for Kubernetes deployment

.PHONY: help deploy deploy-dev deploy-prod status logs clean validate helm-install helm-upgrade

# Default target
help:
	@echo "Liberu Control Panel - Kubernetes Deployment"
	@echo ""
	@echo "Available targets:"
	@echo "  deploy-dev       Deploy to development environment"
	@echo "  deploy-prod      Deploy to production environment"
	@echo "  validate         Validate Kubernetes manifests"
	@echo "  status           Check deployment status"
	@echo "  logs             View application logs"
	@echo "  migrate          Run database migrations"
	@echo "  seed             Seed database"
	@echo "  shell            Open shell in application pod"
	@echo "  helm-install     Install using Helm chart"
	@echo "  helm-upgrade     Upgrade Helm release"
	@echo "  clean            Remove deployment"
	@echo ""

# Validate manifests
validate:
	@echo "Validating Kubernetes manifests..."
	@kubectl apply -k k8s/overlays/development --dry-run=client
	@kubectl apply -k k8s/overlays/production --dry-run=client
	@echo "Validation successful!"

# Deploy to development
deploy-dev:
	@echo "Deploying to development environment..."
	@kubectl apply -k k8s/overlays/development
	@kubectl wait --for=condition=available --timeout=300s deployment/control-panel -n control-panel-dev || true
	@echo "Development deployment complete!"

# Deploy to production
deploy-prod:
	@echo "Deploying to production environment..."
	@kubectl apply -k k8s/overlays/production
	@kubectl wait --for=condition=available --timeout=300s deployment/control-panel -n control-panel || true
	@echo "Production deployment complete!"

# Check deployment status
status:
	@echo "=== Pods ==="
	@kubectl get pods -n control-panel -l app=liberu-control-panel
	@echo ""
	@echo "=== Services ==="
	@kubectl get svc -n control-panel
	@echo ""
	@echo "=== Ingress ==="
	@kubectl get ingress -n control-panel
	@echo ""
	@echo "=== HPA ==="
	@kubectl get hpa -n control-panel

# View logs
logs:
	@kubectl logs -n control-panel -l app=control-panel,component=application -c php-fpm --tail=100 -f

# Run migrations
migrate:
	@echo "Running migrations..."
	@POD=$$(kubectl get pods -n control-panel -l app=control-panel,component=application -o jsonpath='{.items[0].metadata.name}'); \
	kubectl exec -n control-panel $$POD -c php-fpm -- php artisan migrate --force
	@echo "Migrations complete!"

# Seed database
seed:
	@echo "Seeding database..."
	@POD=$$(kubectl get pods -n control-panel -l app=control-panel,component=application -o jsonpath='{.items[0].metadata.name}'); \
	kubectl exec -n control-panel $$POD -c php-fpm -- php artisan db:seed
	@echo "Seeding complete!"

# Open shell
shell:
	@POD=$$(kubectl get pods -n control-panel -l app=control-panel,component=application -o jsonpath='{.items[0].metadata.name}'); \
	kubectl exec -it -n control-panel $$POD -c php-fpm -- /bin/sh

# Helm install
helm-install:
	@echo "Installing with Helm..."
	@helm install control-panel ./helm/control-panel \
		--namespace control-panel \
		--create-namespace
	@echo "Helm installation complete!"

# Helm upgrade
helm-upgrade:
	@echo "Upgrading Helm release..."
	@helm upgrade control-panel ./helm/control-panel \
		--namespace control-panel
	@echo "Helm upgrade complete!"

# Clean up
clean:
	@echo "Removing deployment..."
	@kubectl delete namespace control-panel
	@kubectl delete namespace control-panel-dev
	@echo "Cleanup complete!"
