# Monitoring Scripts

System monitoring and health check tools for the Liberu Control Panel.

## Setup Script

**Location**: `scripts/monitoring/setup-monitoring.sh`

### What It Does

1. Installs monitoring tools (sysstat, htop, iotop, net-tools)
2. Creates health check script
3. Creates performance monitoring script
4. Sets up automated health checks (systemd timer)
5. Configures log rotation

### Installation

```bash
# Run setup script
sudo ./scripts/monitoring/setup-monitoring.sh
```

## Health Check Script

**Location**: `/usr/local/bin/control-panel-health-check` (after setup)

### Features

- Checks critical services (NGINX, PHP-FPM, Database, Redis)
- Monitors disk space usage
- Monitors memory usage
- Tests database connectivity
- Checks queue workers (optional)
- Logs results
- Sends alerts on failures

### Manual Execution

```bash
# Run health check
sudo control-panel-health-check

# View results
cat /var/log/control-panel-monitor/health.log
```

### Automated Execution

The setup script creates a systemd timer that runs every 5 minutes:

```bash
# Check timer status
systemctl status control-panel-health.timer

# List all timers
systemctl list-timers

# View execution logs
journalctl -u control-panel-health -f
```

### Configuration

Set environment variable for email alerts:

```bash
# Add to /etc/environment or service file
ALERT_EMAIL=admin@example.com
```

### Health Check Thresholds

Edit `/usr/local/bin/control-panel-health-check` to adjust:

```bash
# Disk space threshold (default: 90%)
threshold=90

# Memory threshold (default: 90%)
threshold=90
```

## Performance Monitoring Script

**Location**: `/usr/local/bin/control-panel-performance` (after setup)

### Features

- System load average
- Memory usage statistics
- Disk usage statistics
- Top processes by CPU and memory
- Service status (NGINX, PHP-FPM, Database)
- Recent error logs
- Network connections

### Usage

```bash
# Run performance report
sudo control-panel-performance

# Save to file
sudo control-panel-performance > /tmp/performance-report.txt

# Email report
sudo control-panel-performance | mail -s "Performance Report" admin@example.com
```

### Automated Reports

Add to crontab for regular reports:

```bash
# Edit root crontab
sudo crontab -e

# Add daily report at 8 AM
0 8 * * * /usr/local/bin/control-panel-performance | mail -s "Daily Performance Report" admin@example.com

# Or save to file
0 */6 * * * /usr/local/bin/control-panel-performance >> /var/log/control-panel-monitor/performance-$(date +\%Y\%m\%d).log
```

## Monitoring Tools Installed

### 1. sysstat

Collects system performance statistics.

```bash
# View CPU statistics
sar

# View memory statistics
sar -r

# View I/O statistics
sar -b

# Historical data (last 7 days by default)
sar -u -f /var/log/sysstat/sa$(date +%d)
```

### 2. htop

Interactive process viewer.

```bash
# Launch htop
htop

# Sort by memory: Shift+M
# Sort by CPU: Shift+P
# Kill process: F9
# Filter: F4
```

### 3. iotop

Monitor I/O usage by process.

```bash
# Launch iotop
sudo iotop

# Only show processes doing I/O
sudo iotop -o

# Batch mode for logging
sudo iotop -b -n 1 > /tmp/io-usage.txt
```

### 4. net-tools

Network monitoring utilities.

```bash
# Show all connections
netstat -tupn

# Show listening ports
netstat -tulpn

# Show network statistics
netstat -s
```

## Log Files

### Health Check Logs
```bash
# Main log file
tail -f /var/log/control-panel-monitor/health.log

# Systemd journal
journalctl -u control-panel-health -f

# Check for failures
grep "NOT running" /var/log/control-panel-monitor/health.log
```

### Application Logs
```bash
# Laravel logs
tail -f /var/www/control-panel/storage/logs/laravel.log

# NGINX access log
tail -f /var/log/nginx/control-panel-access.log

# NGINX error log
tail -f /var/log/nginx/control-panel-error.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log
```

### System Logs
```bash
# System messages
tail -f /var/log/syslog

# Authentication logs
tail -f /var/log/auth.log

# Kernel messages
dmesg -T -w
```

## Log Rotation

The setup script configures automatic log rotation:

- **Control panel monitor logs**: Daily rotation, keep 7 days
- **Laravel logs**: Daily rotation, keep 14 days
- **Compression**: Enabled (delayed by 1 day)

Configuration file: `/etc/logrotate.d/control-panel`

## Alerts and Notifications

### Email Alerts

Set up email alerts for health check failures:

```bash
# Install mail utilities
sudo apt-get install mailutils  # Ubuntu/Debian
sudo dnf install mailx          # RHEL/AlmaLinux

# Configure mail relay (if needed)
sudo dpkg-reconfigure postfix

# Set alert email
export ALERT_EMAIL=admin@example.com

# Test email
echo "Test alert" | mail -s "Test" $ALERT_EMAIL
```

### Webhook Notifications

Configure webhook for integration with Slack, Discord, etc.:

```bash
# Set webhook URL in health check script
BACKUP_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Test webhook
curl -X POST $BACKUP_WEBHOOK_URL \
  -H 'Content-Type: application/json' \
  -d '{"text":"Test notification from control panel"}'
```

### Monitoring Dashboards

#### Grafana + Prometheus

For advanced monitoring, consider setting up Grafana:

```bash
# Install Prometheus Node Exporter
wget https://github.com/prometheus/node_exporter/releases/download/v1.7.0/node_exporter-1.7.0.linux-amd64.tar.gz
tar xvfz node_exporter-1.7.0.linux-amd64.tar.gz
sudo cp node_exporter-1.7.0.linux-amd64/node_exporter /usr/local/bin/
sudo useradd -rs /bin/false node_exporter

# Create systemd service
sudo tee /etc/systemd/system/node_exporter.service << 'EOF'
[Unit]
Description=Node Exporter
After=network.target

[Service]
User=node_exporter
Group=node_exporter
Type=simple
ExecStart=/usr/local/bin/node_exporter

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable node_exporter
sudo systemctl start node_exporter
```

## Monitoring Checklist

### Daily
- [ ] Review health check logs
- [ ] Check disk space
- [ ] Review error logs
- [ ] Check backup completion

### Weekly
- [ ] Review performance trends
- [ ] Check for failed services
- [ ] Review security logs
- [ ] Test backup restoration

### Monthly
- [ ] Review resource utilization trends
- [ ] Update monitoring thresholds if needed
- [ ] Review and optimize performance
- [ ] Update monitoring tools

## Troubleshooting

### Health Checks Failing

```bash
# Check which service failed
journalctl -u control-panel-health -n 50

# Manually test each component
systemctl status nginx
systemctl status php8.3-fpm
systemctl status mysql
systemctl status redis
```

### High CPU Usage

```bash
# Find top CPU processes
htop  # Press Shift+P to sort by CPU

# Check Apache/NGINX workers
ps aux | grep -E '(nginx|php-fpm)' | wc -l

# Review slow queries
sudo mysqldumpslow /var/log/mysql/slow-query.log
```

### High Memory Usage

```bash
# Find memory hogs
htop  # Press Shift+M to sort by memory

# Check for memory leaks
ps aux --sort=-%mem | head -n 10

# Clear cache if needed
sync && echo 3 | sudo tee /proc/sys/vm/drop_caches
```

### Disk Space Issues

```bash
# Find largest directories
du -sh /* | sort -hr | head -n 10

# Find large files
find / -type f -size +100M -exec ls -lh {} \; 2>/dev/null

# Clean old logs
sudo journalctl --vacuum-time=7d
sudo find /var/log -name "*.log" -mtime +30 -delete
```

## Best Practices

1. **Regular monitoring** - Check dashboards daily
2. **Set up alerts** - Don't wait for issues to be reported
3. **Trend analysis** - Look for gradual degradation
4. **Capacity planning** - Monitor growth and plan upgrades
5. **Document baselines** - Know what "normal" looks like
6. **Test alerts** - Ensure notifications work
7. **Review logs** - Regular log analysis prevents issues
8. **Keep tools updated** - Update monitoring tools regularly

## Integration with External Services

### Uptime Monitoring

- [UptimeRobot](https://uptimerobot.com/) - Free tier available
- [Pingdom](https://www.pingdom.com/)
- [StatusCake](https://www.statuscake.com/)

### Log Management

- [Papertrail](https://www.papertrail.com/)
- [Loggly](https://www.loggly.com/)
- [ELK Stack](https://www.elastic.co/elastic-stack/) - Self-hosted

### APM (Application Performance Monitoring)

- [New Relic](https://newrelic.com/)
- [Datadog](https://www.datadoghq.com/)
- [AppDynamics](https://www.appdynamics.com/)

## Additional Resources

- [Linux Performance Tuning](https://www.brendangregg.com/linuxperf.html)
- [Laravel Performance](https://laravel.com/docs/deployment#optimization)
- [NGINX Monitoring](https://www.nginx.com/blog/monitoring-nginx/)
- [MySQL Performance](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
