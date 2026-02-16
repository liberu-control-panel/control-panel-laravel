# Load Balancing Configuration Guide

This guide covers advanced load balancing configurations for the Liberu Control Panel on Kubernetes, including NGINX Ingress and AWS EKS Load Balancers.

## Table of Contents

1. [Overview](#overview)
2. [NGINX Ingress Load Balancing](#nginx-ingress-load-balancing)
3. [AWS EKS Load Balancing](#aws-eks-load-balancing)
4. [Session Affinity](#session-affinity)
5. [Health Checks](#health-checks)
6. [Performance Optimization](#performance-optimization)
7. [Common Scenarios](#common-scenarios)
8. [Troubleshooting](#troubleshooting)

## Overview

The control panel supports multiple load balancing strategies for high availability and optimal performance:

- **NGINX Ingress Controller**: Default, works on all Kubernetes clusters
- **AWS ALB (Application Load Balancer)**: Layer 7 load balancing for EKS
- **AWS NLB (Network Load Balancer)**: Layer 4 load balancing for EKS
- **Service-level Load Balancing**: Kubernetes native load balancing

### Load Balancing Algorithms

- **Round Robin**: Distributes requests evenly across pods (default)
- **Least Connections**: Routes to pod with fewest active connections
- **IP Hash**: Routes based on client IP for session persistence
- **Cookie-based**: Uses cookies for sticky sessions

## NGINX Ingress Load Balancing

### Basic Configuration

The default Helm values include NGINX Ingress optimizations:

```yaml
ingress:
  enabled: true
  className: "nginx"
  annotations:
    # Session affinity with cookies
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "INGRESSCOOKIE"
    nginx.ingress.kubernetes.io/session-cookie-max-age: "10800"  # 3 hours
    
    # Consistent hashing for session persistence
    nginx.ingress.kubernetes.io/upstream-hash-by: "$binary_remote_addr"
    
    # Connection settings
    nginx.ingress.kubernetes.io/proxy-connect-timeout: "300"
    nginx.ingress.kubernetes.io/proxy-read-timeout: "300"
    nginx.ingress.kubernetes.io/proxy-send-timeout: "300"
    
    # Rate limiting
    nginx.ingress.kubernetes.io/limit-connections: "100"
    nginx.ingress.kubernetes.io/limit-rps: "100"
```

### Advanced NGINX Configurations

#### Least Connections Algorithm

For uneven workload distribution, use least connections:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/load-balance: "least_conn"
```

#### Custom Health Checks

Configure custom health check endpoints:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/health-check-path: "/health"
    nginx.ingress.kubernetes.io/health-check-interval: "30s"
```

#### Connection Pooling

Optimize backend connections with connection pooling:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/upstream-keepalive-connections: "64"
    nginx.ingress.kubernetes.io/upstream-keepalive-timeout: "60"
    nginx.ingress.kubernetes.io/upstream-keepalive-requests: "100"
```

#### Rate Limiting per IP

Protect against DDoS with per-IP rate limiting:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/limit-rps: "10"
    nginx.ingress.kubernetes.io/limit-burst-multiplier: "5"
    nginx.ingress.kubernetes.io/limit-rate-after: "10m"
    nginx.ingress.kubernetes.io/limit-rate: "100k"
```

## AWS EKS Load Balancing

### Application Load Balancer (ALB)

ALB provides Layer 7 (HTTP/HTTPS) load balancing with advanced routing.

#### Installation

First, install the AWS Load Balancer Controller:

```bash
# Create IAM policy
curl -o iam_policy.json https://raw.githubusercontent.com/kubernetes-sigs/aws-load-balancer-controller/v2.7.0/docs/install/iam_policy.json

aws iam create-policy \
  --policy-name AWSLoadBalancerControllerIAMPolicy \
  --policy-document file://iam_policy.json

# Install controller
helm repo add eks https://aws.github.io/eks-charts
helm repo update

helm install aws-load-balancer-controller eks/aws-load-balancer-controller \
  -n kube-system \
  --set clusterName=your-cluster-name \
  --set serviceAccount.create=true \
  --set serviceAccount.name=aws-load-balancer-controller
```

#### ALB Ingress Configuration

Update `values.yaml` for ALB:

```yaml
ingress:
  enabled: true
  className: "alb"
  annotations:
    # ALB specific settings
    alb.ingress.kubernetes.io/scheme: internet-facing
    alb.ingress.kubernetes.io/target-type: ip
    alb.ingress.kubernetes.io/load-balancer-attributes: |
      idle_timeout.timeout_seconds=300,
      routing.http2.enabled=true,
      routing.http.drop_invalid_header_fields.enabled=true
    
    # Health checks
    alb.ingress.kubernetes.io/healthcheck-path: /health
    alb.ingress.kubernetes.io/healthcheck-interval-seconds: "15"
    alb.ingress.kubernetes.io/healthcheck-timeout-seconds: "5"
    alb.ingress.kubernetes.io/healthy-threshold-count: "2"
    alb.ingress.kubernetes.io/unhealthy-threshold-count: "2"
    alb.ingress.kubernetes.io/success-codes: "200-399"
    
    # SSL/TLS
    alb.ingress.kubernetes.io/certificate-arn: arn:aws:acm:region:account:certificate/xxxxx
    alb.ingress.kubernetes.io/listen-ports: '[{"HTTP": 80}, {"HTTPS": 443}]'
    alb.ingress.kubernetes.io/ssl-redirect: "443"
    
    # Sticky sessions
    alb.ingress.kubernetes.io/target-group-attributes: |
      stickiness.enabled=true,
      stickiness.lb_cookie.duration_seconds=86400,
      deregistration_delay.timeout_seconds=30
```

#### Advanced ALB Features

**Path-based Routing:**
```yaml
alb.ingress.kubernetes.io/conditions.api: |
  [{"field":"path-pattern","pathPatternConfig":{"values":["/api/*"]}}]
alb.ingress.kubernetes.io/actions.api: |
  {"type":"forward","targetGroupARN": "arn:aws:elasticloadbalancing:..."}
```

**Host-based Routing:**
```yaml
alb.ingress.kubernetes.io/conditions.host: |
  [{"field":"host-header","hostHeaderConfig":{"values":["api.example.com"]}}]
```

**WAF Integration:**
```yaml
alb.ingress.kubernetes.io/wafv2-acl-arn: arn:aws:wafv2:region:account:regional/webacl/name/id
```

### Network Load Balancer (NLB)

NLB provides Layer 4 (TCP/UDP) load balancing for lower latency.

#### NLB Service Configuration

Update the service for NLB:

```yaml
service:
  type: LoadBalancer
  annotations:
    service.beta.kubernetes.io/aws-load-balancer-type: "nlb"
    service.beta.kubernetes.io/aws-load-balancer-cross-zone-load-balancing-enabled: "true"
    service.beta.kubernetes.io/aws-load-balancer-backend-protocol: "http"
    service.beta.kubernetes.io/aws-load-balancer-healthcheck-healthy-threshold: "2"
    service.beta.kubernetes.io/aws-load-balancer-healthcheck-unhealthy-threshold: "2"
    service.beta.kubernetes.io/aws-load-balancer-healthcheck-interval: "10"
    # Static IP
    service.beta.kubernetes.io/aws-load-balancer-eip-allocations: eipalloc-xxxxxxxxx,eipalloc-yyyyyyyyy
```

## Session Affinity

### Kubernetes Service Session Affinity

Enable at the Service level:

```yaml
service:
  type: ClusterIP
  sessionAffinity: ClientIP
  sessionAffinityTimeout: 10800  # 3 hours
```

This ensures requests from the same client IP are routed to the same pod.

### NGINX Cookie-Based Affinity

For more reliable session persistence across load balancers:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "INGRESSCOOKIE"
    nginx.ingress.kubernetes.io/session-cookie-max-age: "10800"
    nginx.ingress.kubernetes.io/session-cookie-secure: "true"
    nginx.ingress.kubernetes.io/session-cookie-samesite: "Lax"
    nginx.ingress.kubernetes.io/session-cookie-path: "/"
    nginx.ingress.kubernetes.io/session-cookie-change-on-failure: "true"
```

### ALB Sticky Sessions

For AWS ALB:

```yaml
alb.ingress.kubernetes.io/target-group-attributes: |
  stickiness.enabled=true,
  stickiness.type=lb_cookie,
  stickiness.lb_cookie.duration_seconds=86400
```

## Health Checks

### Application Health Check Endpoint

The control panel should expose a health check endpoint. Add to your Laravel routes:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});
```

### Kubernetes Probes

Update deployment with health probes:

```yaml
# In deployment.yaml
livenessProbe:
  httpGet:
    path: /health
    port: http
  initialDelaySeconds: 30
  periodSeconds: 10
  timeoutSeconds: 5
  successThreshold: 1
  failureThreshold: 3

readinessProbe:
  httpGet:
    path: /health
    port: http
  initialDelaySeconds: 10
  periodSeconds: 5
  timeoutSeconds: 3
  successThreshold: 1
  failureThreshold: 3
```

### ALB Health Checks

```yaml
alb.ingress.kubernetes.io/healthcheck-path: /health
alb.ingress.kubernetes.io/healthcheck-protocol: HTTP
alb.ingress.kubernetes.io/healthcheck-port: traffic-port
alb.ingress.kubernetes.io/healthcheck-interval-seconds: "15"
alb.ingress.kubernetes.io/healthcheck-timeout-seconds: "5"
alb.ingress.kubernetes.io/healthy-threshold-count: "2"
alb.ingress.kubernetes.io/unhealthy-threshold-count: "2"
alb.ingress.kubernetes.io/success-codes: "200"
```

## Performance Optimization

### Connection Settings

Optimize connection handling:

```yaml
ingress:
  annotations:
    # Buffer settings
    nginx.ingress.kubernetes.io/proxy-buffering: "on"
    nginx.ingress.kubernetes.io/proxy-buffer-size: "8k"
    nginx.ingress.kubernetes.io/proxy-buffers-number: "4"
    nginx.ingress.kubernetes.io/client-body-buffer-size: "128k"
    
    # Timeout settings
    nginx.ingress.kubernetes.io/proxy-connect-timeout: "300"
    nginx.ingress.kubernetes.io/proxy-read-timeout: "300"
    nginx.ingress.kubernetes.io/proxy-send-timeout: "300"
    nginx.ingress.kubernetes.io/client-header-timeout: "300"
    nginx.ingress.kubernetes.io/client-body-timeout: "300"
    
    # Keep-alive
    nginx.ingress.kubernetes.io/upstream-keepalive-connections: "64"
    nginx.ingress.kubernetes.io/upstream-keepalive-timeout: "60"
```

### HTTP/2 and gRPC

Enable HTTP/2 for better performance:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/http2-push-preload: "true"
    # For gRPC
    nginx.ingress.kubernetes.io/backend-protocol: "GRPC"
```

### Compression

Enable gzip compression:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/enable-gzip: "true"
    nginx.ingress.kubernetes.io/gzip-level: "5"
    nginx.ingress.kubernetes.io/gzip-types: "text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript"
```

### Caching

Enable proxy caching:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/proxy-cache-valid: "200 302 10m"
    nginx.ingress.kubernetes.io/proxy-cache-valid: "404 1m"
    nginx.ingress.kubernetes.io/proxy-cache-key: "$scheme$proxy_host$request_uri"
    nginx.ingress.kubernetes.io/proxy-cache-methods: "GET HEAD"
```

## Common Scenarios

### Scenario 1: High-Traffic Production (NGINX Ingress)

```yaml
ingress:
  enabled: true
  className: "nginx"
  annotations:
    # Session persistence
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "route"
    nginx.ingress.kubernetes.io/session-cookie-max-age: "3600"
    
    # Performance
    nginx.ingress.kubernetes.io/upstream-keepalive-connections: "100"
    nginx.ingress.kubernetes.io/enable-gzip: "true"
    nginx.ingress.kubernetes.io/gzip-level: "6"
    
    # Rate limiting
    nginx.ingress.kubernetes.io/limit-rps: "200"
    nginx.ingress.kubernetes.io/limit-connections: "200"
    
    # SSL
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"

service:
  sessionAffinity: ClientIP
  sessionAffinityTimeout: 3600

autoscaling:
  enabled: true
  minReplicas: 3
  maxReplicas: 20
  targetCPUUtilizationPercentage: 70
```

### Scenario 2: AWS EKS with ALB

```yaml
ingress:
  enabled: true
  className: "alb"
  annotations:
    alb.ingress.kubernetes.io/scheme: internet-facing
    alb.ingress.kubernetes.io/target-type: ip
    alb.ingress.kubernetes.io/healthcheck-path: /health
    alb.ingress.kubernetes.io/healthcheck-interval-seconds: "15"
    alb.ingress.kubernetes.io/healthy-threshold-count: "2"
    alb.ingress.kubernetes.io/unhealthy-threshold-count: "2"
    alb.ingress.kubernetes.io/success-codes: "200-399"
    alb.ingress.kubernetes.io/load-balancer-attributes: |
      idle_timeout.timeout_seconds=300,
      routing.http2.enabled=true
    alb.ingress.kubernetes.io/target-group-attributes: |
      stickiness.enabled=true,
      stickiness.lb_cookie.duration_seconds=3600,
      deregistration_delay.timeout_seconds=30,
      slow_start.duration_seconds=30
    alb.ingress.kubernetes.io/listen-ports: '[{"HTTP": 80}, {"HTTPS": 443}]'
    alb.ingress.kubernetes.io/ssl-redirect: "443"

autoscaling:
  enabled: true
  minReplicas: 3
  maxReplicas: 50
  targetCPUUtilizationPercentage: 60
```

### Scenario 3: Multi-Region Setup

For multi-region deployments with GeoDNS:

```yaml
# Region 1 (us-west-2)
ingress:
  hosts:
    - host: us-west.control-panel.example.com
  annotations:
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "REGION_US_WEST"

# Region 2 (eu-west-1)
ingress:
  hosts:
    - host: eu-west.control-panel.example.com
  annotations:
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "REGION_EU_WEST"
```

Then use Route53 or similar for GeoDNS routing to regional endpoints.

### Scenario 4: WebSocket Support

For applications using WebSockets:

```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/proxy-read-timeout: "3600"
    nginx.ingress.kubernetes.io/proxy-send-timeout: "3600"
    nginx.ingress.kubernetes.io/websocket-services: "control-panel"
    nginx.ingress.kubernetes.io/connection-proxy-header: "upgrade"
    nginx.ingress.kubernetes.io/affinity: "cookie"
    nginx.ingress.kubernetes.io/session-cookie-name: "WEBSOCKET"
```

## Troubleshooting

### Common Issues

#### 1. Sticky Sessions Not Working

**Symptoms**: Users lose session between requests

**Solution**:
```bash
# Check if cookies are being set
curl -v https://your-domain.com | grep -i set-cookie

# Verify ingress annotations
kubectl describe ingress -n control-panel

# Check service affinity
kubectl get service control-panel -n control-panel -o yaml | grep -A5 sessionAffinity
```

#### 2. High Latency

**Symptoms**: Slow response times

**Diagnosis**:
```bash
# Check pod distribution
kubectl get pods -n control-panel -o wide

# Check ingress metrics
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller | grep latency

# Monitor with metrics
kubectl top pods -n control-panel
```

**Solutions**:
- Enable keepalive connections
- Increase buffer sizes
- Add more replicas
- Check network policies

#### 3. Uneven Load Distribution

**Symptoms**: Some pods receive more traffic than others

**Solutions**:
```yaml
# Use least connections algorithm
nginx.ingress.kubernetes.io/load-balance: "least_conn"

# Or enable connection limit
nginx.ingress.kubernetes.io/limit-connections: "50"
```

#### 4. Health Check Failures

**Symptoms**: Pods marked unhealthy, traffic not routed

**Diagnosis**:
```bash
# Check pod logs
kubectl logs -n control-panel deployment/control-panel

# Test health endpoint
kubectl exec -n control-panel deployment/control-panel -- curl localhost/health

# Check ingress backend health
kubectl describe ingress -n control-panel
```

### Monitoring

#### Check Load Balancer Status

**NGINX Ingress:**
```bash
# Get ingress controller logs
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller

# Check upstream status
kubectl exec -n ingress-nginx deployment/ingress-nginx-controller -- curl localhost:18080/nginx_status
```

**AWS ALB:**
```bash
# Get ALB ARN
kubectl get ingress -n control-panel -o jsonpath='{.status.loadBalancer.ingress[0].hostname}'

# Check target health in AWS Console or CLI
aws elbv2 describe-target-health --target-group-arn <arn>
```

#### Metrics to Monitor

- Request latency (p50, p95, p99)
- Error rates (4xx, 5xx)
- Active connections
- Backend response times
- Pod CPU/Memory usage
- Health check success rate

## Best Practices

1. **Always enable health checks** - Ensure unhealthy pods don't receive traffic
2. **Use session affinity for stateful apps** - Maintain user experience
3. **Set appropriate timeouts** - Balance responsiveness and reliability
4. **Enable rate limiting** - Protect against abuse
5. **Monitor continuously** - Set up alerts for anomalies
6. **Test failover scenarios** - Ensure high availability works
7. **Use autoscaling** - Handle traffic spikes automatically
8. **Enable compression** - Reduce bandwidth usage
9. **Configure SSL properly** - Use cert-manager for auto-renewal
10. **Document your setup** - Make troubleshooting easier

## References

- [NGINX Ingress Controller Annotations](https://kubernetes.github.io/ingress-nginx/user-guide/nginx-configuration/annotations/)
- [AWS Load Balancer Controller](https://kubernetes-sigs.github.io/aws-load-balancer-controller/)
- [Kubernetes Service Documentation](https://kubernetes.io/docs/concepts/services-networking/service/)
- [Kubernetes Ingress Documentation](https://kubernetes.io/docs/concepts/services-networking/ingress/)
