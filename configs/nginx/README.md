# NGINX Configuration

Optimized NGINX configuration for the Liberu Control Panel.

## Configuration File

- **Location**: `configs/nginx/control-panel.conf`
- **Purpose**: Production-ready NGINX configuration optimized for Laravel applications

## Features

### Performance Optimizations
- HTTP/2 support for faster page loads
- Gzip compression (6 levels) for text content
- Brotli compression support (if module available)
- FastCGI buffering and caching
- Static file caching with 1-year expiration
- Keep-alive connections

### Security Hardening
- Automatic HTTP to HTTPS redirect
- TLS 1.2 and 1.3 only (no older protocols)
- Strong cipher suites (ECDHE preferred)
- Security headers:
  - HSTS (HTTP Strict Transport Security)
  - X-Frame-Options
  - X-Content-Type-Options
  - X-XSS-Protection
  - Referrer-Policy
  - Content-Security-Policy
  - Permissions-Policy
- SSL session caching and OCSP stapling
- Hidden files (.git, .env) blocked
- Sensitive files (composer.json, etc.) blocked
- PHP files in upload directories blocked

### Laravel Optimizations
- Proper URL rewriting for clean URLs
- FastCGI parameter optimization
- PHP-FPM socket connection (faster than TCP)
- Long execution timeouts for large operations
- Large upload support (100MB default)

### Rate Limiting
- General rate limit: 10 requests/second (burst 20)
- Login rate limit: 5 requests/minute (burst 3)
- Protects against DoS and brute force attacks

### Health Checks
- Health check endpoints exempt from access logs
- Optimized for Kubernetes/Docker health probes

## Installation

### 1. Copy Configuration

```bash
# Ubuntu/Debian
sudo cp configs/nginx/control-panel.conf /etc/nginx/sites-available/control-panel
sudo ln -s /etc/nginx/sites-available/control-panel /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default

# RHEL/AlmaLinux/Rocky
sudo cp configs/nginx/control-panel.conf /etc/nginx/conf.d/control-panel.conf
```

### 2. Customize Configuration

Edit the configuration file and update:

```nginx
server_name your-domain.com;  # Line 18
root /var/www/control-panel/public;  # Line 19

ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;  # Line 22
ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;  # Line 23
```

### 3. Test Configuration

```bash
sudo nginx -t
```

### 4. Reload NGINX

```bash
sudo systemctl reload nginx
```

## SSL Certificate Setup

### Using Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-nginx  # Ubuntu/Debian
sudo dnf install certbot python3-certbot-nginx      # RHEL/AlmaLinux

# Obtain certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Certbot will automatically configure NGINX
# Auto-renewal is configured via systemd timer
```

### Manual SSL Certificate

If using a purchased certificate:

```bash
# Place certificate files
sudo mkdir -p /etc/nginx/ssl
sudo cp your-certificate.crt /etc/nginx/ssl/
sudo cp your-private-key.key /etc/nginx/ssl/

# Update nginx config
ssl_certificate /etc/nginx/ssl/your-certificate.crt;
ssl_certificate_key /etc/nginx/ssl/your-private-key.key;
```

## PHP-FPM Configuration

### Unix Socket (Recommended - Faster)

Default configuration uses Unix socket:
```nginx
fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
```

Ensure PHP-FPM is configured to use the same socket in `/etc/php/8.3/fpm/pool.d/www.conf`:
```ini
listen = /var/run/php/php8.3-fpm.sock
```

### TCP Socket (Alternative)

If preferring TCP connection, update line 138:
```nginx
fastcgi_pass 127.0.0.1:9000;
```

And configure PHP-FPM accordingly.

## Performance Tuning

### For High Traffic Sites

Increase worker connections in `/etc/nginx/nginx.conf`:
```nginx
events {
    worker_connections 4096;
}
```

### Enable Brotli Compression

If brotli module is installed, uncomment lines 68-73:
```nginx
brotli on;
brotli_comp_level 6;
brotli_types text/plain text/css ...;
```

### Adjust Upload Size

For larger uploads, modify line 42:
```nginx
client_max_body_size 500M;  # Increase from 100M
```

Also update PHP configuration:
```ini
upload_max_filesize = 500M
post_max_size = 500M
```

## Rate Limiting Configuration

### Adjust Rate Limits

Modify zones (lines 59-61):
```nginx
# More permissive
limit_req_zone $binary_remote_addr zone=general:10m rate=20r/s;
limit_req_zone $binary_remote_addr zone=login:10m rate=10r/m;

# More restrictive
limit_req_zone $binary_remote_addr zone=general:10m rate=5r/s;
limit_req_zone $binary_remote_addr zone=login:10m rate=3r/m;
```

### Whitelist IP Addresses

Add before rate limiting:
```nginx
geo $limit {
    default 1;
    192.168.1.0/24 0;  # Don't rate limit internal network
    10.0.0.100 0;      # Don't rate limit specific IP
}

map $limit $limit_key {
    0 "";
    1 $binary_remote_addr;
}

limit_req_zone $limit_key zone=general:10m rate=10r/s;
```

## Monitoring

### Access Logs
```bash
# Real-time access log
sudo tail -f /var/log/nginx/control-panel-access.log

# Filter by status code
sudo grep "HTTP/1.1\" 500" /var/log/nginx/control-panel-access.log
```

### Error Logs
```bash
# Real-time error log
sudo tail -f /var/log/nginx/control-panel-error.log

# Count errors by type
sudo grep "error" /var/log/nginx/control-panel-error.log | wc -l
```

### Performance Metrics
```bash
# Check active connections
curl http://localhost/nginx_status  # If stub_status enabled

# Request rate
sudo tail -f /var/log/nginx/control-panel-access.log | pv -l -i 10 -r > /dev/null
```

## Troubleshooting

### 502 Bad Gateway
- Check PHP-FPM is running: `sudo systemctl status php8.3-fpm`
- Verify socket path matches: `ls -la /var/run/php/`
- Check PHP-FPM logs: `sudo tail -f /var/log/php8.3-fpm.log`

### 413 Request Entity Too Large
- Increase `client_max_body_size`
- Also increase PHP limits

### 403 Forbidden
- Check file permissions: `ls -la /var/www/control-panel/public`
- Verify NGINX user has access
- Check SELinux context (RHEL): `ls -Z /var/www/control-panel/public`

### Slow Response Times
- Check PHP-FPM pool settings
- Increase FastCGI buffers
- Enable caching (Redis/Memcached)
- Check database queries

## Security Best Practices

1. **Keep NGINX updated** for security patches
2. **Use strong SSL certificates** from trusted CA
3. **Enable HSTS** after testing (already configured)
4. **Monitor logs** for suspicious activity
5. **Regular security audits** using tools like:
   - `nmap` for port scanning
   - `nikto` for web vulnerabilities
   - SSL Labs for SSL configuration testing
6. **Implement fail2ban** for additional protection
7. **Use Web Application Firewall (WAF)** for advanced protection

## Additional Resources

- [NGINX Documentation](https://nginx.org/en/docs/)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [SSL Labs Server Test](https://www.ssllabs.com/ssltest/)
