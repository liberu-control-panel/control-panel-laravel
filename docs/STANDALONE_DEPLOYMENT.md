# Standalone Deployment Guide

This guide explains how to use the Control Panel in standalone mode, where services are deployed directly to the host system rather than using Docker or Kubernetes.

## Overview

The Control Panel supports three deployment modes:
- **Kubernetes** (recommended for production)
- **Docker Compose** (for development/small-scale)
- **Standalone** (traditional server setup)

In standalone mode, the control panel manages system services directly:
- Nginx for web serving
- PHP-FPM for PHP processing
- MySQL/PostgreSQL for databases
- Certbot for SSL certificates

## Prerequisites

### Required System Services

Before using standalone mode, ensure these services are installed and running:

```bash
# Nginx
sudo apt install nginx
sudo systemctl enable nginx
sudo systemctl start nginx

# PHP-FPM (install desired versions)
sudo apt install php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring
sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm

# MySQL (or MariaDB)
sudo apt install mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql

# PostgreSQL (optional, if using PostgreSQL)
sudo apt install postgresql
sudo systemctl enable postgresql
sudo systemctl start postgresql

# Certbot for Let's Encrypt SSL
sudo apt install certbot python3-certbot-nginx
```

### Web Server User Permissions

The control panel needs sudo permissions for certain operations. Create a sudoers file:

```bash
# Create sudoers file for the web server user
sudo visudo -f /etc/sudoers.d/control-panel

# Add these lines (replace www-data with your web server user if different):
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot
www-data ALL=(ALL) NOPASSWD: /bin/mv * /etc/nginx/sites-available/*
www-data ALL=(ALL) NOPASSWD: /bin/ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled/*
www-data ALL=(ALL) NOPASSWD: /bin/rm /etc/nginx/sites-available/*
www-data ALL=(ALL) NOPASSWD: /bin/rm /etc/nginx/sites-enabled/*
www-data ALL=(ALL) NOPASSWD: /bin/chmod * /etc/nginx/sites-available/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/mysql
www-data ALL=(ALL) NOPASSWD: /usr/bin/psql
```

**Security Note**: For production environments, consider using more restrictive sudoers rules or a dedicated management service account.

## How Standalone Mode Works

### Deployment Detection

The control panel automatically detects the deployment mode:

```php
use App\Services\DeploymentDetectionService;

$detectionService = app(DeploymentDetectionService::class);
$mode = $detectionService->detectDeploymentMode();
// Returns: 'standalone', 'docker-compose', or 'kubernetes'
```

Standalone mode is detected when:
- No `/.dockerenv` file exists
- No Kubernetes service account is found
- No Docker cgroup is detected

### Service Architecture

In standalone mode, services work as follows:

#### 1. Web Server Service
- Generates Nginx configuration files
- Deploys configs to `/etc/nginx/sites-available/`
- Creates symlinks in `/etc/nginx/sites-enabled/`
- Uses Unix sockets for PHP-FPM: `unix:/var/run/php/php8.2-fpm.sock`
- Reloads Nginx using `systemctl reload nginx`

#### 2. Database Service
- Executes MySQL/PostgreSQL commands directly on the system
- Uses native command-line tools (`mysql`, `psql`)
- Manages databases and users without Docker containers

#### 3. SSL Service
- Uses system Certbot for Let's Encrypt certificates
- Certificates stored in `/etc/letsencrypt/live/{domain}/`
- Nginx configs reference Let's Encrypt certificate paths

## Usage Examples

### Deploying a Domain

```php
use App\Models\Domain;
use App\Models\Server;
use App\Services\DeploymentAwareService;

// Get or create a standalone server
$server = Server::where('type', Server::TYPE_STANDALONE)->first();

// Create domain
$domain = Domain::create([
    'domain_name' => 'example.com',
    'server_id' => $server->id,
    'user_id' => auth()->id(),
]);

// Deploy with options
$deploymentService = app(DeploymentAwareService::class);
$result = $deploymentService->deployDomain($domain, [
    'php_version' => '8.2',
    'document_root' => '/var/www/example.com/public',
    'enable_ssl' => true,
    'create_database' => true,
    'database_name' => 'example_db',
    'database_engine' => 'mysql',
]);
```

This will:
1. Create Nginx virtual host configuration
2. Test the configuration
3. Reload Nginx
4. Request Let's Encrypt SSL certificate
5. Update Nginx configuration with SSL
6. Create MySQL database

### Creating a Database

```php
use App\Models\Domain;
use App\Services\DatabaseService;

$domain = Domain::find(1);
$databaseService = app(DatabaseService::class);

// Create database
$database = $databaseService->createDatabase($domain, [
    'name' => 'my_database',
    'engine' => 'mysql', // or 'postgresql'
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

// Create database user
$user = $databaseService->createDatabaseUser($database, [
    'username' => 'db_user',
    'password' => 'secure_password',
    'host' => 'localhost',
    'privileges' => ['ALL'],
]);
```

### Managing SSL Certificates

```php
use App\Models\Domain;
use App\Services\SslService;

$domain = Domain::find(1);
$sslService = app(SslService::class);

// Generate Let's Encrypt certificate
$certificate = $sslService->generateLetsEncryptCertificate($domain, [
    'email' => 'admin@example.com',
    'include_www' => true,
    'webroot' => '/var/www/example.com/public',
]);

// Renew certificate
$success = $sslService->renewLetsEncryptCertificate($certificate);

// Check certificate status
$status = $sslService->checkCertificateStatus($certificate);
```

### Getting Deployment Status

```php
use App\Models\Domain;
use App\Services\DeploymentAwareService;

$domain = Domain::find(1);
$deploymentService = app(DeploymentAwareService::class);

$status = $deploymentService->getDeploymentStatus($domain);
/*
Returns:
[
    'status' => 'running', // or 'stopped', 'error'
    'details' => [
        'method' => 'standalone',
        'nginx_config_exists' => true,
        'nginx_running' => true,
        'ssl_enabled' => true,
    ]
]
*/
```

### Restarting a Domain

```php
use App\Models\Domain;
use App\Services\DeploymentAwareService;

$domain = Domain::find(1);
$deploymentService = app(DeploymentAwareService::class);

// This will test Nginx config and reload if valid
$success = $deploymentService->restartDomain($domain);
```

### Deleting a Domain

```php
use App\Models\Domain;
use App\Services\DeploymentAwareService;

$domain = Domain::find(1);
$deploymentService = app(DeploymentAwareService::class);

// This will remove Nginx config and reload Nginx
$success = $deploymentService->deleteDomain($domain);
```

## File Locations

### Nginx Configurations
- **Available sites**: `/etc/nginx/sites-available/{domain_name}`
- **Enabled sites**: `/etc/nginx/sites-enabled/{domain_name}` (symlink)

### SSL Certificates
- **Let's Encrypt**: `/etc/letsencrypt/live/{domain_name}/`
  - `fullchain.pem` - Full certificate chain
  - `privkey.pem` - Private key
  - `chain.pem` - Certificate chain
  - `cert.pem` - Domain certificate

### PHP-FPM Sockets
- **PHP 8.2**: `/var/run/php/php8.2-fpm.sock`
- **PHP 8.1**: `/var/run/php/php8.1-fpm.sock`
- **PHP 8.0**: `/var/run/php/php8.0-fpm.sock`

### Document Roots
- Default: `/var/www/{domain_name}/public`
- Customizable per domain

## Nginx Configuration Example

Here's an example of the generated Nginx configuration for standalone mode:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    server_name example.com www.example.com;
    root /var/www/example.com/public;
    index index.php index.html index.htm;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # PHP handling
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_read_timeout 300;
    }

    # Static files
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # WordPress specific rules
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;
    return 301 https://$server_name$request_uri;
}
```

## Troubleshooting

### Check Nginx Configuration

```bash
sudo nginx -t
```

### Check Nginx Status

```bash
sudo systemctl status nginx
```

### Check PHP-FPM Status

```bash
sudo systemctl status php8.2-fpm
```

### View Nginx Logs

```bash
# Access log
sudo tail -f /var/log/nginx/{domain_name}_access.log

# Error log
sudo tail -f /var/log/nginx/{domain_name}_error.log
```

### Check SSL Certificate

```bash
sudo certbot certificates
```

### Test SSL Configuration

```bash
openssl s_client -connect example.com:443 -servername example.com
```

### Common Issues

#### Permission Denied Errors
- Ensure sudoers file is configured correctly
- Check web server user permissions
- Verify file ownership in `/etc/nginx/sites-available/`

#### PHP-FPM Socket Not Found
- Ensure PHP-FPM is installed and running
- Check socket path in `/var/run/php/`
- Verify PHP version matches configuration

#### SSL Certificate Not Issued
- Check domain DNS points to server
- Ensure port 80 is accessible for ACME challenge
- Review Certbot logs: `sudo cat /var/log/letsencrypt/letsencrypt.log`

#### Database Connection Failed
- Verify MySQL/PostgreSQL is running
- Check database user permissions
- Test connection: `mysql -u user -p database_name`

## Migration from Docker to Standalone

To migrate from Docker to standalone mode:

1. **Export databases** from Docker containers
2. **Stop Docker services**
3. **Install system services** (Nginx, PHP-FPM, MySQL)
4. **Import databases** to system MySQL/PostgreSQL
5. **Copy web files** from Docker volumes to system directories
6. **Update server type** in database: `Server::TYPE_STANDALONE`
7. **Re-deploy domains** using the control panel

## Security Considerations

### File Permissions
- Nginx configs: `644` (rw-r--r--)
- SSL certificates: `600` (rw-------)
- Document root: `755` (rwxr-xr-x)
- PHP files: `644` (rw-r--r--)

### Firewall Rules
```bash
# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow SSH (if needed)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

### Database Security
```bash
# Run MySQL secure installation
sudo mysql_secure_installation

# Set root password
# Remove anonymous users
# Disallow root login remotely
# Remove test database
```

## Performance Optimization

### Nginx Tuning

Edit `/etc/nginx/nginx.conf`:

```nginx
worker_processes auto;
worker_rlimit_nofile 65535;

events {
    worker_connections 4096;
    use epoll;
    multi_accept on;
}

http {
    # Enable caching
    open_file_cache max=10000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;
    
    # Compression
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
}
```

### PHP-FPM Tuning

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### MySQL Tuning

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 200
query_cache_size = 64M
```

## Conclusion

Standalone mode provides a traditional, system-level approach to hosting management. While Docker and Kubernetes offer better isolation and scalability, standalone mode is:
- ✅ Simpler to set up on single servers
- ✅ Lower resource overhead
- ✅ Easier to debug with familiar tools
- ✅ Compatible with traditional hosting environments

Choose standalone mode when:
- Running on a single dedicated server
- Working with existing infrastructure
- Requiring direct system access
- Managing a small number of sites

For larger deployments or multi-server environments, consider Kubernetes or Docker Compose modes.
