# SSH Configuration Guide

This guide explains how to configure SSH connections for managing remote Kubernetes or Docker servers.

## Overview

The control panel uses SSH to connect to remote servers and execute commands like `kubectl` for Kubernetes deployments. All SSH credentials are encrypted and stored securely in the database.

## Authentication Methods

### 1. SSH Key Authentication (Recommended)

SSH key authentication is more secure than password authentication.

#### Generate SSH Key Pair

You can generate a new SSH key pair through the control panel UI or manually:

```bash
# Generate RSA key (2048-bit or higher)
ssh-keygen -t rsa -b 4096 -C "control-panel@example.com"

# Or generate ED25519 key (recommended for better security)
ssh-keygen -t ed25519 -C "control-panel@example.com"
```

#### Add Public Key to Remote Server

Copy your public key to the remote server:

```bash
# Method 1: Using ssh-copy-id
ssh-copy-id -i ~/.ssh/id_ed25519.pub user@remote-server

# Method 2: Manual
cat ~/.ssh/id_ed25519.pub | ssh user@remote-server "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
```

#### Add Credential in Control Panel

1. Navigate to **Servers** → **Create Server**
2. Fill in server details (hostname, port, IP)
3. Select **SSH Key** as authentication type
4. Paste your **private key** content
5. (Optional) Enter passphrase if your key is encrypted
6. Save

The private key will be encrypted before storage.

### 2. Password Authentication

While supported, password authentication is less secure than SSH keys.

#### Setup

1. Navigate to **Servers** → **Create Server**
2. Fill in server details
3. Select **Password** as authentication type
4. Enter username and password
5. Save

Passwords are encrypted using Laravel's Crypt facade.

### 3. Both (Key + Password)

Some servers require both SSH key and password (e.g., for sudo operations).

## SSH Configuration

### Server Requirements

The SSH user on the remote server must have permission to:

1. Execute `kubectl` commands (for Kubernetes servers)
2. Execute `docker` and `docker-compose` commands (for Docker servers)
3. Read/write to temporary directories for manifest files

### Recommended SSH User Setup

Create a dedicated user for the control panel:

```bash
# On remote server
sudo useradd -m -s /bin/bash controlpanel
sudo usermod -aG docker controlpanel  # For Docker access

# Add kubectl permissions for Kubernetes
sudo mkdir -p /home/controlpanel/.kube
sudo cp /root/.kube/config /home/controlpanel/.kube/
sudo chown -R controlpanel:controlpanel /home/controlpanel/.kube
```

### Limited Sudo Access (Optional)

If you need sudo for certain commands, create a sudoers file:

```bash
# /etc/sudoers.d/controlpanel
controlpanel ALL=(ALL) NOPASSWD: /usr/local/bin/kubectl
controlpanel ALL=(ALL) NOPASSWD: /usr/bin/docker
controlpanel ALL=(ALL) NOPASSWD: /usr/bin/systemctl
```

Then enable sudo in control panel `.env`:

```env
SSH_SUDO_ENABLED=true
SSH_SUDO_PASSWORD_REQUIRED=false
```

## Security Best Practices

### 1. Use SSH Keys

Always prefer SSH keys over passwords:
- More secure (4096-bit RSA or ED25519)
- Can't be brute-forced
- Can be easily rotated

### 2. Protect Private Keys

- Add passphrase to private keys
- Store keys with restricted permissions (chmod 600)
- Rotate keys regularly (every 90 days recommended)

### 3. Limit SSH User Permissions

- Create dedicated user with minimal permissions
- Use sudo only for specific commands
- Enable fail2ban on SSH server

### 4. Secure SSH Daemon

Configure `/etc/ssh/sshd_config` on remote server:

```
# Disable password authentication (if using keys only)
PasswordAuthentication no

# Disable root login
PermitRootLogin no

# Use strong ciphers only
Ciphers aes256-ctr,aes192-ctr,aes128-ctr
MACs hmac-sha2-256,hmac-sha2-512

# Change default port (security through obscurity)
Port 2222
```

### 5. Connection Pooling

The control panel maintains a connection pool to reduce SSH overhead:

```env
# Configure in .env
SSH_CONNECTION_POOL_SIZE=10
SSH_KEEPALIVE_INTERVAL=60
SSH_TIMEOUT=30
SSH_RETRY_ATTEMPTS=3
SSH_RETRY_DELAY=5
```

## Testing Connections

### Test SSH Connection

After adding a server, test the connection:

1. Navigate to **Servers** → Select your server
2. Click **Test Connection**
3. Review the result

Or use the SSH service directly:

```php
use App\Services\SshConnectionService;
use App\Models\Server;

$server = Server::find(1);
$sshService = app(SshConnectionService::class);

if ($sshService->testConnection($server)) {
    echo "Connection successful!";
} else {
    echo "Connection failed!";
}
```

### Test kubectl Access

```bash
# On remote server, as the SSH user
kubectl get nodes
kubectl get namespaces
```

## Troubleshooting

### Connection Timeout

**Problem**: SSH connection times out

**Solutions**:
- Check firewall rules (port 22 must be open)
- Verify SSH service is running: `sudo systemctl status sshd`
- Increase timeout in `.env`: `SSH_TIMEOUT=60`

### Authentication Failed

**Problem**: SSH authentication fails

**Solutions**:
- Verify public key is in `~/.ssh/authorized_keys` on remote server
- Check private key matches public key
- Ensure private key file permissions are correct (600)
- Check SSH daemon logs: `sudo tail -f /var/log/auth.log`

### Permission Denied

**Problem**: Commands fail with permission denied

**Solutions**:
- Verify user has docker/kubectl permissions
- Check sudoers configuration if using sudo
- Ensure kubeconfig is readable

### Key Passphrase Issues

**Problem**: Encrypted private key won't connect

**Solutions**:
- Verify passphrase is correct
- Try uploading key without passphrase (less secure)
- Generate new key pair

## Advanced: Multiple Servers

You can connect to multiple servers for load balancing or high availability:

1. Add multiple servers in control panel
2. Mark one as **default server**
3. Assign domains to specific servers or use automatic load balancing

### Example Multi-Server Setup

```
Server 1: k8s-prod-1 (default, max 100 domains)
Server 2: k8s-prod-2 (max 100 domains)
Server 3: k8s-dev (development only)
```

Domains will be automatically distributed across available servers.

## Credential Rotation

Regular credential rotation improves security:

### Rotate SSH Keys

1. Generate new key pair
2. Add new public key to server (don't remove old yet)
3. Update credential in control panel
4. Test connection
5. Remove old public key from server

### Rotate Passwords

1. Change password on remote server
2. Update credential in control panel
3. Test connection

## References

- [OpenSSH Documentation](https://www.openssh.com/manual.html)
- [SSH Best Practices](https://www.ssh.com/academy/ssh/best-practices)
- [Laravel Encryption](https://laravel.com/docs/encryption)
