# Nginx Configuration Guide

This guide explains the production-ready nginx configuration used in the Liberu Control Panel helm chart.

## Table of Contents

- [Overview](#overview)
- [Production Features](#production-features)
- [Configuration Details](#configuration-details)
- [Performance Optimizations](#performance-optimizations)
- [Security Headers](#security-headers)
- [Customization](#customization)
- [Troubleshooting](#troubleshooting)

## Overview

The control panel uses nginx as a reverse proxy to the PHP-FPM application server. The nginx configuration has been optimized for production use with security headers, compression, and caching.

**Location:** `helm/control-panel/templates/nginx-configmap.yaml`

## Production Features

The nginx configuration includes the following production-ready features:

### ✅ Performance Optimizations
- **Gzip Compression**: Reduces bandwidth by 60-80%
- **Sendfile**: Enabled for 2-3x faster file serving
- **TCP Optimizations**: `tcp_nopush` and `tcp_nodelay` enabled
- **Connection Pooling**: Keepalive with 65s timeout
- **Static Asset Caching**: 1-year cache for images, CSS, JS, fonts

### ✅ Security Features
- **Security Headers**: OWASP-recommended headers
- **Hidden File Protection**: Denies access to `.` files
- **Content Security Policy**: XSS and injection protection
- **Frame Options**: Clickjacking protection

### ✅ Laravel Integration
- **Clean URLs**: Laravel routing with `try_files`
- **PHP-FPM**: Optimized FastCGI configuration
- **Health Checks**: Kubernetes-compatible `/health` endpoint

## Configuration Details

### Gzip Compression

```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript 
           application/json application/javascript 
           application/xml+rss application/rss+xml 
           font/truetype font/opentype 
           application/vnd.ms-fontobject image/svg+xml;
gzip_disable "msie6";
```

**Benefits:**
- Reduces response size by 60-80% for text-based content
- Compression level 6 provides good balance between CPU usage and compression ratio
- Excludes binary files (images, PDFs) that don't compress well
- IE6 compatibility disabled (modern browsers only)

### Connection Optimization

```nginx
keepalive_timeout 65;
keepalive_requests 100;
```

**Benefits:**
- Reuses TCP connections for multiple requests
- Reduces latency and server overhead
- Allows up to 100 requests per connection

### Security Headers

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
```

**Header Explanations:**

| Header | Purpose | Protection Against |
|--------|---------|-------------------|
| **X-Frame-Options** | Prevents page from being loaded in iframe | Clickjacking attacks |
| **X-Content-Type-Options** | Prevents MIME type sniffing | Drive-by downloads, content injection |
| **X-XSS-Protection** | Enables browser XSS filter | Cross-site scripting (legacy browsers) |
| **Referrer-Policy** | Controls referrer information | Privacy leaks |
| **Content-Security-Policy** | Controls resource loading | XSS, data injection, code injection |

### Static File Caching

```nginx
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}
```

**Benefits:**
- Browsers cache static assets for 1 year
- Reduces server load and bandwidth
- `immutable` directive tells browsers the file never changes
- Disables access logs for static files to reduce I/O

### File Serving Optimizations

```nginx
sendfile on;
tcp_nopush on;
tcp_nodelay on;
```

**Benefits:**
- `sendfile`: Uses kernel sendfile() for 2-3x faster file transfers
- `tcp_nopush`: Optimizes packet sending (reduces packets)
- `tcp_nodelay`: Disables Nagle's algorithm for low latency

### PHP-FPM Configuration

```nginx
location ~ \.php$ {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_intercept_errors off;
    fastcgi_buffer_size 16k;
    fastcgi_buffers 4 16k;
    fastcgi_connect_timeout 300;
    fastcgi_send_timeout 300;
    fastcgi_read_timeout 300;
}
```

**Key Settings:**
- **Buffer size**: 16k × 4 = 64k total buffering
- **Timeouts**: 300s (5 minutes) for long-running requests
- **Path info**: Proper handling for Laravel routing

### Health Check Endpoint

```nginx
location /health {
    access_log off;
    return 200 "healthy\n";
    add_header Content-Type text/plain;
}
```

**Purpose:**
- Used by Kubernetes liveness and readiness probes
- Fast response without hitting PHP-FPM
- Doesn't generate access logs

## Performance Optimizations

### Expected Performance Improvements

| Optimization | Impact | Benefit |
|--------------|--------|---------|
| **Gzip Compression** | 60-80% smaller responses | Faster page loads, reduced bandwidth costs |
| **Sendfile** | 2-3x faster file serving | Lower CPU usage, faster static file delivery |
| **Static Caching** | 100% cache hit rate | Reduced server requests, faster repeat visits |
| **Keepalive** | Reduced latency | Faster subsequent requests from same client |
| **Security Headers** | Minimal overhead | Protection with no performance cost |

### Bandwidth Reduction Example

**Without compression:**
- HTML page: 50 KB
- CSS files: 100 KB
- JS files: 200 KB
- **Total**: 350 KB

**With gzip (comp_level 6):**
- HTML page: 10 KB (80% reduction)
- CSS files: 20 KB (80% reduction)
- JS files: 50 KB (75% reduction)
- **Total**: 80 KB (77% reduction)

**Annual savings for 1M pageviews:**
- Data transfer: 270 GB saved
- AWS S3 egress savings: ~$24/year
- User experience: Faster load times

## Customization

### Adjusting Compression Level

To change gzip compression level, edit `nginx-configmap.yaml`:

```yaml
# Lower compression (faster, less compression)
gzip_comp_level 3;  # Light compression

# Higher compression (slower, more compression)
gzip_comp_level 9;  # Maximum compression
```

**Recommendations:**
- **Level 1-3**: Low CPU usage, good for high-traffic sites
- **Level 4-6**: Balanced (recommended for most sites)
- **Level 7-9**: High compression, high CPU usage

### Adding Custom Headers

To add custom headers, edit the server block:

```nginx
add_header X-Custom-Header "value" always;
add_header Strict-Transport-Security "max-age=31536000" always;  # HSTS
```

### Modifying Cache Duration

To change static asset cache duration:

```nginx
# Short cache (1 week)
expires 7d;

# Medium cache (1 month)
expires 30d;

# Long cache (1 year) - default
expires 1y;
```

### File Upload Limits

Default is 100MB. To increase:

```nginx
client_max_body_size 500m;  # Allow 500MB uploads
```

**Note:** Also update PHP settings to match.

### PHP Timeout Adjustments

For longer-running scripts:

```nginx
fastcgi_connect_timeout 600;  # 10 minutes
fastcgi_send_timeout 600;
fastcgi_read_timeout 600;
```

## Troubleshooting

### Issue: 413 Request Entity Too Large

**Cause:** File upload exceeds `client_max_body_size`

**Solution:**
```nginx
client_max_body_size 200m;  # Increase limit
```

Also check PHP settings:
```ini
upload_max_filesize = 200M
post_max_size = 200M
```

### Issue: 504 Gateway Timeout

**Cause:** PHP-FPM request takes longer than timeout

**Solutions:**

1. **Increase nginx timeouts:**
```nginx
fastcgi_read_timeout 600;  # 10 minutes
```

2. **Increase PHP max_execution_time:**
```ini
max_execution_time = 600
```

3. **Optimize slow code** (preferred solution)

### Issue: Static Files Not Caching

**Cause:** Browser not respecting cache headers

**Debug:**
```bash
# Check response headers
curl -I https://your-domain.com/css/app.css

# Should show:
# Cache-Control: public, immutable
# Expires: (date 1 year in future)
```

**Solution:** Verify cache-busting in asset URLs (Laravel Mix adds hashes automatically)

### Issue: Gzip Not Working

**Cause:** Content type not in gzip_types list

**Debug:**
```bash
# Check if gzip is enabled
curl -H "Accept-Encoding: gzip" -I https://your-domain.com

# Should show:
# Content-Encoding: gzip
```

**Solution:** Add content type to `gzip_types` list

### Issue: CSP Blocking Resources

**Cause:** Content Security Policy too restrictive

**Solution:** Adjust CSP header to allow required resources:

```nginx
# Allow specific domains
add_header Content-Security-Policy "default-src 'self' https://cdn.example.com; script-src 'self' 'unsafe-inline' https://cdn.example.com" always;
```

**Tools:**
- Use browser developer tools to see CSP violations
- Test CSP at: https://csp-evaluator.withgoogle.com/

## Security Best Practices

### 1. Enable HTTPS Only (via Ingress)

The nginx configuration serves HTTP on port 80, but the Kubernetes Ingress should enforce HTTPS:

```yaml
# In values.yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
```

### 2. Add HSTS Header (After HTTPS is working)

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

**Warning:** Only enable HSTS after confirming HTTPS works properly!

### 3. Restrict CSP Further

For production, tighten the CSP policy:

```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;" always;
```

### 4. Hide Nginx Version

Add to nginx config:

```nginx
server_tokens off;
```

### 5. Rate Limiting

For API endpoints or login pages:

```nginx
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

location /api/ {
    limit_req zone=api burst=20 nodelay;
}
```

## Monitoring and Metrics

### Key Metrics to Monitor

1. **Response Time**: Should be <200ms for cached assets
2. **Compression Ratio**: Should be 60-80% for HTML/CSS/JS
3. **Cache Hit Rate**: Should be >90% for static assets
4. **Error Rate**: Monitor 4xx and 5xx errors

### Nginx Access Log Format

Add to ConfigMap for structured logging:

```nginx
log_format json_combined escape=json
  '{'
    '"time_local":"$time_local",'
    '"remote_addr":"$remote_addr",'
    '"request":"$request",'
    '"status": "$status",'
    '"body_bytes_sent":"$body_bytes_sent",'
    '"request_time":"$request_time",'
    '"http_referrer":"$http_referer",'
    '"http_user_agent":"$http_user_agent"'
  '}';

access_log /var/log/nginx/access.log json_combined;
```

### Prometheus Metrics

Install nginx-prometheus-exporter for metrics:

```bash
kubectl apply -f https://raw.githubusercontent.com/nginxinc/nginx-prometheus-exporter/main/deployments/deployment.yaml
```

## HTTP/2 Support

HTTP/2 is typically enabled at the Ingress Controller level, not in the nginx sidecar.

**Verify HTTP/2 is enabled:**
```bash
curl -I --http2 https://your-domain.com
# Should show: HTTP/2 200
```

**Enable in NGINX Ingress Controller:**
```yaml
ingress:
  annotations:
    nginx.ingress.kubernetes.io/http2-push-preload: "true"
```

## Related Documentation

- [Helm Chart Values](../helm/control-panel/values.yaml)
- [Kubernetes Setup Guide](KUBERNETES_SETUP.md)
- [Performance Tuning](PERFORMANCE_TUNING.md) (if exists)
- [Security Guide](SECURITY.md)

## References

- [OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [Mozilla Security Headers](https://infosec.mozilla.org/guidelines/web_security)
