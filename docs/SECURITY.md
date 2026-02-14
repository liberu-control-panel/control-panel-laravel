# Security Best Practices

This document outlines security best practices for deploying and managing the Liberu Control Panel.

## Table of Contents

1. [SSH Security](#ssh-security)
2. [Kubernetes Security](#kubernetes-security)
3. [Application Security](#application-security)
4. [Network Security](#network-security)
5. [Data Protection](#data-protection)
6. [Monitoring and Auditing](#monitoring-and-auditing)

## SSH Security

### Authentication

**Use SSH Keys (Not Passwords)**

SSH keys provide better security than passwords:

```bash
# Generate strong SSH key
ssh-keygen -t ed25519 -C "controlpanel@example.com"

# Or 4096-bit RSA
ssh-keygen -t rsa -b 4096 -C "controlpanel@example.com"
```

**Protect Private Keys**

- Always add a passphrase to private keys
- Store keys with restrictive permissions:
  ```bash
  chmod 600 ~/.ssh/id_ed25519
  chmod 644 ~/.ssh/id_ed25519.pub
  ```
- Never share private keys
- Rotate keys every 90 days

### SSH Server Hardening

Configure `/etc/ssh/sshd_config` on remote servers:

```
# Disable password authentication
PasswordAuthentication no
ChallengeResponseAuthentication no

# Disable root login
PermitRootLogin no

# Use strong ciphers and MACs
Ciphers aes256-ctr,aes192-ctr,aes128-ctr
MACs hmac-sha2-256,hmac-sha2-512
KexAlgorithms curve25519-sha256,diffie-hellman-group-exchange-sha256

# Limit authentication attempts
MaxAuthTries 3
MaxSessions 5

# Enable strict mode
StrictModes yes

# Set login grace time
LoginGraceTime 60

# Change default port (optional)
Port 2222
```

Restart SSH after changes:
```bash
sudo systemctl restart sshd
```

### Limit User Permissions

Create a dedicated user with minimal permissions:

```bash
# Create user
sudo useradd -m -s /bin/bash controlpanel

# Limited sudo (only necessary commands)
echo "controlpanel ALL=(ALL) NOPASSWD: /usr/local/bin/kubectl" | sudo tee /etc/sudoers.d/controlpanel
```

### Connection Security

Configure in `.env`:

```env
# Use connection pooling to reduce overhead
SSH_CONNECTION_POOL_SIZE=10

# Set reasonable timeouts
SSH_TIMEOUT=30
SSH_RETRY_ATTEMPTS=3

# Enable only secure ciphers
SSH_ALLOWED_CIPHERS=aes256-ctr,aes192-ctr,aes128-ctr
SSH_ALLOWED_MACS=hmac-sha2-256,hmac-sha2-512
```

## Kubernetes Security

### RBAC (Role-Based Access Control)

**Always enable RBAC** for all namespaces. The control panel automatically creates:

1. **ServiceAccount** - Identity for pods
2. **Role** - Limited permissions within namespace
3. **RoleBinding** - Binds ServiceAccount to Role

Example role with minimal permissions:

```yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: hosting-user-role
  namespace: hosting-example-com
rules:
- apiGroups: [""]
  resources: ["pods", "pods/log"]
  verbs: ["get", "list"]
- apiGroups: [""]
  resources: ["services"]
  verbs: ["get", "list"]
```

### Network Policies

**Enable NetworkPolicies** to isolate traffic:

```env
KUBERNETES_ENABLE_NETWORK_POLICIES=true
```

Default policies applied:
1. **Deny all ingress** by default
2. **Allow** ingress from NGINX controller
3. **Allow** internal namespace communication
4. **Allow** DNS egress

### Pod Security

**Run as non-root**:

```env
KUBERNETES_RUN_AS_NON_ROOT=true
```

The control panel sets:
```yaml
securityContext:
  runAsNonRoot: true
  runAsUser: 1000
  fsGroup: 1000
```

**Drop capabilities**:

```yaml
securityContext:
  capabilities:
    drop:
    - ALL
```

**Read-only root filesystem** (optional):

```env
KUBERNETES_READONLY_ROOTFS=true
```

### Resource Limits

**Always set resource limits** to prevent:
- Resource exhaustion attacks
- Noisy neighbor problems
- Cluster instability

Default limits:

```php
'default_resources' => [
    'requests' => [
        'memory' => '128Mi',
        'cpu' => '100m',
    ],
    'limits' => [
        'memory' => '512Mi',
        'cpu' => '500m',
    ],
],
```

### Secrets Management

**Never store secrets in plain text**. The control panel:

1. Encrypts credentials with Laravel Crypt
2. Stores in Kubernetes Secrets
3. Mounts as environment variables or volumes

```yaml
env:
- name: DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: example-com-credentials
      key: db_password
```

### Image Security

**Use trusted images**:

```php
'images' => [
    'nginx' => 'nginx:alpine',  // Official images
    'php_fpm' => 'php:8.2-fpm-alpine',
    'mysql' => 'mysql:8.0',
],
```

**Scan images** for vulnerabilities:

```bash
# Using Trivy
trivy image nginx:alpine
```

**Use specific tags** (not `latest`):

```yaml
image: nginx:1.25-alpine  # ✓ Good
image: nginx:latest       # ✗ Bad
```

## Application Security

### Laravel Security

**Strong APP_KEY**:

```bash
php artisan key:generate
```

**Environment file**:

```bash
# Protect .env file
chmod 600 .env
chown www-data:www-data .env
```

**Database credentials**:

```env
# Use strong passwords
DB_PASSWORD=<generate-with-pwgen-or-1password>
```

**HTTPS only**:

```env
# Force HTTPS in production
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

### Authentication

**Enable 2FA** for all users:

1. Install Filament 2FA package
2. Require 2FA for admin users
3. Set session timeout

```env
SESSION_LIFETIME=120  # 2 hours
```

### Input Validation

Always validate and sanitize user input:

```php
// Use Laravel validation
$validated = $request->validate([
    'domain_name' => 'required|string|max:255|regex:/^[a-z0-9.-]+$/',
    'email' => 'required|email',
]);
```

### SQL Injection Prevention

Use Eloquent ORM or prepared statements:

```php
// ✓ Good - Uses parameter binding
Domain::where('domain_name', $name)->first();

// ✗ Bad - SQL injection risk
DB::select("SELECT * FROM domains WHERE domain_name = '$name'");
```

### XSS Prevention

Laravel escapes output by default:

```blade
{{-- Escaped (safe) --}}
{{ $domain->name }}

{{-- Unescaped (dangerous) --}}
{!! $html !!}  {{-- Only use with trusted data --}}
```

### CSRF Protection

Laravel includes CSRF protection:

```blade
<form method="POST">
    @csrf
    ...
</form>
```

## Network Security

### Firewall Rules

**Control panel server**:

```bash
# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow SSH (change port if needed)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

**Kubernetes nodes**:

```bash
# Allow K8s API
sudo ufw allow 6443/tcp

# Allow kubelet
sudo ufw allow 10250/tcp

# Allow NodePort range (if needed)
sudo ufw allow 30000:32767/tcp
```

### SSL/TLS

**Always use HTTPS**:

1. Enable cert-manager
2. Use Let's Encrypt
3. Auto-renew certificates

```yaml
metadata:
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
```

**TLS version**:

```
# Ingress annotation for TLS 1.2+
nginx.ingress.kubernetes.io/ssl-protocols: "TLSv1.2 TLSv1.3"
```

### DDoS Protection

**Rate limiting**:

```yaml
metadata:
  annotations:
    nginx.ingress.kubernetes.io/limit-rps: "100"
    nginx.ingress.kubernetes.io/limit-connections: "10"
```

**Use CloudFlare** or similar CDN for DDoS protection.

## Data Protection

### Database Security

**Encryption at rest**:

```env
# Use encrypted storage volumes
KUBERNETES_STORAGE_CLASS=encrypted-ssd
```

**Backup strategy**:

1. Daily automated backups
2. Store in different location
3. Encrypt backups
4. Test restore procedure

```bash
# Backup MySQL
kubectl exec -n hosting-example-com example-com-db-0 -- \
  mysqldump -u root -p$MYSQL_ROOT_PASSWORD dbname > backup.sql

# Encrypt backup
gpg --encrypt --recipient admin@example.com backup.sql
```

### Credential Storage

**Encryption**:

All credentials are encrypted:

```php
// Automatic encryption in model
public function setPasswordAttribute($value)
{
    $this->attributes['password'] = Crypt::encryptString($value);
}
```

**Key rotation**:

Rotate encryption keys regularly:

```bash
# Generate new key
php artisan key:generate --show

# Update APP_KEY in .env
# Re-encrypt existing data if needed
```

### Audit Logging

Log all security-relevant events:

```php
Log::info("User {$user->id} deployed domain {$domain->domain_name}");
Log::warning("Failed SSH connection attempt to {$server->hostname}");
```

## Monitoring and Auditing

### Security Monitoring

**Monitor logs**:

```bash
# Application logs
tail -f storage/logs/laravel.log

# SSH access logs
sudo tail -f /var/log/auth.log

# Kubernetes events
kubectl get events --all-namespaces --watch
```

**Set up alerts**:

1. Failed login attempts
2. Failed SSH connections
3. Unusual resource usage
4. Certificate expiration

### Vulnerability Scanning

**Scan regularly**:

```bash
# Scan dependencies
composer audit

# Scan Docker images
trivy image php:8.2-fpm-alpine

# Scan Kubernetes
kube-bench
```

### Compliance

**Regular security audits**:

1. Review user permissions quarterly
2. Rotate credentials every 90 days
3. Update dependencies monthly
4. Penetration testing annually

**Keep records**:

- Who accessed what
- When deployments occurred
- Changes to security policies

## Security Checklist

### Initial Setup

- [ ] Change default passwords
- [ ] Enable 2FA for all admin users
- [ ] Configure SSH with keys only
- [ ] Set up firewall rules
- [ ] Enable HTTPS with valid certificates
- [ ] Configure automated backups
- [ ] Set resource limits
- [ ] Enable RBAC and NetworkPolicies

### Regular Maintenance

- [ ] Update dependencies (monthly)
- [ ] Rotate SSH keys (quarterly)
- [ ] Review access logs (weekly)
- [ ] Test backup restoration (quarterly)
- [ ] Scan for vulnerabilities (weekly)
- [ ] Review user permissions (quarterly)
- [ ] Update SSL certificates (automatic with cert-manager)

### Incident Response

If a security incident occurs:

1. **Isolate** affected systems
2. **Document** what happened
3. **Investigate** root cause
4. **Remediate** vulnerabilities
5. **Notify** affected users if required
6. **Review** and improve processes

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CIS Kubernetes Benchmark](https://www.cisecurity.org/benchmark/kubernetes)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [SSH Hardening Guide](https://www.ssh.com/academy/ssh/sshd_config)

## Getting Help

If you discover a security vulnerability:

1. **DO NOT** open a public GitHub issue
2. Email security@liberu.co.uk
3. Provide details and steps to reproduce
4. Allow time for patch before public disclosure

We take security seriously and will respond promptly to reports.
