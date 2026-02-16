# Load Balancing Examples

This directory contains example configurations for different load balancing scenarios.

## Examples

### 1. High-Traffic Production with NGINX Ingress

File: `nginx-high-traffic.yaml`

Optimized for high-traffic production environments with session persistence, rate limiting, and performance optimizations.

### 2. AWS EKS with Application Load Balancer

File: `eks-alb-production.yaml`

Production-ready configuration for AWS EKS using Application Load Balancer with health checks and sticky sessions.

### 3. WebSocket Support

File: `websocket-values.yaml`

Configuration optimized for applications using WebSockets with extended timeouts.

## Usage

Apply these configurations using Helm:

```bash
# Example 1: High-traffic NGINX
helm install control-panel ./helm/control-panel \
  -f examples/nginx-high-traffic.yaml \
  --namespace control-panel \
  --create-namespace

# Example 2: AWS EKS with ALB
helm install control-panel ./helm/control-panel \
  -f examples/eks-alb-production.yaml \
  --namespace control-panel \
  --create-namespace

# Example 3: WebSocket support
helm install control-panel ./helm/control-panel \
  -f examples/websocket-values.yaml \
  --namespace control-panel \
  --create-namespace
```

## Customization

You can combine these examples with your own values:

```bash
helm install control-panel ./helm/control-panel \
  -f examples/nginx-high-traffic.yaml \
  -f my-custom-values.yaml \
  --namespace control-panel \
  --create-namespace
```
