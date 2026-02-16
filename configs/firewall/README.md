# Firewall Configuration Scripts

These scripts configure firewall rules for the Liberu Control Panel in standalone deployments.

## Available Scripts

### 1. UFW (Uncomplicated Firewall) - Ubuntu/Debian

**Usage:**
```bash
sudo ./configs/firewall/setup-ufw.sh
```

**What it does:**
- Configures UFW with secure defaults
- Opens HTTP (80) and HTTPS (443) ports
- Enables SSH with rate limiting (protection against brute force)
- Optionally configures:
  - Mail server ports (SMTP, IMAP, POP3)
  - DNS server (port 53)
  - FTP server (ports 21, 20, passive range)
- Enables logging for monitoring

### 2. FirewallD - RHEL/AlmaLinux/Rocky Linux

**Usage:**
```bash
sudo ./configs/firewall/setup-firewalld.sh
```

**What it does:**
- Configures FirewallD with secure defaults
- Opens HTTP and HTTPS services
- Enables SSH with rate limiting
- Optionally configures:
  - Mail services
  - DNS service
  - FTP service
- Sets public zone as default

## Default Ports

### Always Opened:
- **22**: SSH (with rate limiting)
- **80**: HTTP
- **443**: HTTPS

### Optional (prompted during setup):
- **25**: SMTP (mail)
- **587**: SMTP Submission (mail)
- **465**: SMTPS (mail)
- **143**: IMAP (mail)
- **993**: IMAPS (mail)
- **110**: POP3 (mail)
- **995**: POP3S (mail)
- **53**: DNS (TCP/UDP)
- **21**: FTP
- **20**: FTP Data
- **49152-65534**: FTP Passive Range

## Security Features

### Rate Limiting
Both scripts implement rate limiting for SSH to prevent brute force attacks:
- **UFW**: `ufw limit 22/tcp`
- **FirewallD**: Maximum 10 connections per minute

### Logging
- UFW: Medium level logging
- FirewallD: Default logging enabled

### Default Policies
- **Incoming**: DENY (only explicitly allowed ports are open)
- **Outgoing**: ALLOW (applications can connect outbound)

## Verification

### UFW
```bash
# Check status
sudo ufw status verbose

# List rules
sudo ufw status numbered

# Check logs
sudo tail -f /var/log/ufw.log
```

### FirewallD
```bash
# Check status
sudo firewall-cmd --state

# List active rules
sudo firewall-cmd --list-all

# Check logs
sudo journalctl -u firewalld -f
```

## Customization

To add additional ports after installation:

### UFW
```bash
# Allow single port
sudo ufw allow 8080/tcp

# Allow port range
sudo ufw allow 3000:3010/tcp

# Allow from specific IP
sudo ufw allow from 192.168.1.100 to any port 3306
```

### FirewallD
```bash
# Allow single port
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload

# Allow service
sudo firewall-cmd --permanent --add-service=mysql
sudo firewall-cmd --reload

# Allow from specific IP
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="192.168.1.100" port protocol="tcp" port="3306" accept'
sudo firewall-cmd --reload
```

## Troubleshooting

### Can't connect after enabling firewall
1. Ensure SSH port is open before enabling
2. Test from another terminal before closing current session
3. If locked out, use console access to disable firewall temporarily

### UFW won't start
```bash
# Reset UFW
sudo ufw --force reset
sudo ufw enable
```

### FirewallD conflicts
```bash
# Check for conflicts with other firewall services
sudo systemctl status iptables
sudo systemctl status ufw

# Disable conflicting services
sudo systemctl stop ufw
sudo systemctl disable ufw
```

## Disable Firewall (Not Recommended)

### UFW
```bash
sudo ufw disable
```

### FirewallD
```bash
sudo systemctl stop firewalld
sudo systemctl disable firewalld
```

## Best Practices

1. **Always test SSH access** before disconnecting after firewall configuration
2. **Keep SSH rate limiting enabled** to prevent brute force attacks
3. **Only open required ports** - close unused services
4. **Enable logging** for security monitoring
5. **Regular review** of firewall rules and logs
6. **Use strong SSH keys** instead of password authentication
7. **Consider fail2ban** for additional protection against attacks

## Integration with Control Panel

The control panel can manage firewall rules programmatically. After initial setup with these scripts, the control panel's firewall management features will work within the configured security framework.
