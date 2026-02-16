#!/bin/bash
################################################################################
# Liberu Control Panel - Monitoring Setup Script
# 
# This script sets up basic monitoring using native Linux tools
################################################################################

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   log_error "This script must be run as root"
   exit 1
fi

log_info "╔═══════════════════════════════════════════════════════╗"
log_info "║  Control Panel - Monitoring Setup                    ║"
log_info "╚═══════════════════════════════════════════════════════╝"
echo ""

# Create monitoring directories
MONITOR_DIR="/var/lib/control-panel-monitor"
LOG_DIR="/var/log/control-panel-monitor"

log_info "Creating monitoring directories..."
mkdir -p "$MONITOR_DIR"
mkdir -p "$LOG_DIR"

# Install monitoring tools
log_info "Installing monitoring tools..."

if command -v apt-get &> /dev/null; then
    apt-get update
    apt-get install -y sysstat htop iotop net-tools
elif command -v dnf &> /dev/null; then
    dnf install -y sysstat htop iotop net-tools
fi

# Enable sysstat
systemctl enable sysstat
systemctl start sysstat

# Create health check script
log_info "Creating health check script..."
cat > /usr/local/bin/control-panel-health-check << 'EOF'
#!/bin/bash

# Configuration
APP_DIR="/var/www/control-panel"
LOG_FILE="/var/log/control-panel-monitor/health.log"
ALERT_EMAIL="${ALERT_EMAIL:-}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

check_service() {
    local service="$1"
    if systemctl is-active --quiet "$service"; then
        log "✓ $service is running"
        return 0
    else
        log "✗ $service is NOT running"
        return 1
    fi
}

check_disk_space() {
    local threshold=90
    local usage=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$usage" -lt "$threshold" ]; then
        log "✓ Disk space: ${usage}% used"
        return 0
    else
        log "✗ Disk space critical: ${usage}% used"
        return 1
    fi
}

check_memory() {
    local threshold=90
    local usage=$(free | grep Mem | awk '{print int($3/$2 * 100)}')
    
    if [ "$usage" -lt "$threshold" ]; then
        log "✓ Memory: ${usage}% used"
        return 0
    else
        log "✗ Memory critical: ${usage}% used"
        return 1
    fi
}

check_database() {
    if [ -f "$APP_DIR/.env" ]; then
        source "$APP_DIR/.env"
        
        if [ "$DB_CONNECTION" = "mysql" ]; then
            if mysql -h"${DB_HOST}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" -e "SELECT 1" &> /dev/null; then
                log "✓ MySQL database is accessible"
                return 0
            else
                log "✗ MySQL database is NOT accessible"
                return 1
            fi
        elif [ "$DB_CONNECTION" = "pgsql" ]; then
            if PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" -c "SELECT 1" &> /dev/null; then
                log "✓ PostgreSQL database is accessible"
                return 0
            else
                log "✗ PostgreSQL database is NOT accessible"
                return 1
            fi
        fi
    fi
    return 0
}

check_queue_workers() {
    if systemctl is-active --quiet control-panel-queue; then
        log "✓ Queue workers are running"
        return 0
    else
        log "⚠ Queue workers are not running (this is optional)"
        return 0
    fi
}

# Main health check
main() {
    log "=== Starting Health Check ==="
    
    local failed=0
    
    # Check services
    check_service "nginx" || failed=1
    check_service "php8.3-fpm" || check_service "php-fpm" || failed=1
    check_service "mysql" || check_service "mariadb" || check_service "postgresql" || failed=1
    check_service "redis" || check_service "redis-server" || true
    
    # Check queue workers (optional)
    check_queue_workers || true
    
    # Check resources
    check_disk_space || failed=1
    check_memory || failed=1
    
    # Check database connectivity
    check_database || failed=1
    
    log "=== Health Check Complete ==="
    
    if [ $failed -eq 1 ]; then
        if [ -n "$ALERT_EMAIL" ]; then
            cat "$LOG_FILE" | tail -20 | mail -s "Control Panel Health Check FAILED" "$ALERT_EMAIL"
        fi
        exit 1
    fi
    
    exit 0
}

main
EOF

chmod +x /usr/local/bin/control-panel-health-check

# Create performance monitoring script
log_info "Creating performance monitoring script..."
cat > /usr/local/bin/control-panel-performance << 'EOF'
#!/bin/bash

# Performance report
echo "=== Control Panel Performance Report ==="
echo "Generated: $(date)"
echo ""

echo "=== System Load ==="
uptime
echo ""

echo "=== Memory Usage ==="
free -h
echo ""

echo "=== Disk Usage ==="
df -h / /var
echo ""

echo "=== Top Processes by Memory ==="
ps aux --sort=-%mem | head -n 10
echo ""

echo "=== Top Processes by CPU ==="
ps aux --sort=-%cpu | head -n 10
echo ""

echo "=== NGINX Status ==="
systemctl status nginx --no-pager | head -n 10
echo ""

echo "=== PHP-FPM Status ==="
systemctl status php*-fpm --no-pager | head -n 10 2>/dev/null || systemctl status php-fpm --no-pager | head -n 10
echo ""

echo "=== Database Status ==="
systemctl status mysql --no-pager | head -n 10 2>/dev/null || systemctl status mariadb --no-pager | head -n 10 2>/dev/null || systemctl status postgresql --no-pager | head -n 10
echo ""

echo "=== Recent Error Logs ==="
if [ -f /var/www/control-panel/storage/logs/laravel.log ]; then
    echo "Laravel Errors (last 10 lines):"
    tail -n 10 /var/www/control-panel/storage/logs/laravel.log
fi
echo ""

echo "=== Network Connections ==="
netstat -tupn 2>/dev/null | grep -E '(nginx|php-fpm|mysql|redis)' || ss -tupn | grep -E '(nginx|php-fpm|mysql|redis)'
echo ""
EOF

chmod +x /usr/local/bin/control-panel-performance

# Create systemd timer for health checks
log_info "Creating systemd timer for health checks..."
cat > /etc/systemd/system/control-panel-health.service << EOF
[Unit]
Description=Control Panel Health Check
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/control-panel-health-check
StandardOutput=journal
StandardError=journal
SyslogIdentifier=control-panel-health
EOF

cat > /etc/systemd/system/control-panel-health.timer << EOF
[Unit]
Description=Run Control Panel Health Check every 5 minutes
Requires=control-panel-health.service

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
AccuracySec=1s

[Install]
WantedBy=timers.target
EOF

# Enable and start timer
systemctl daemon-reload
systemctl enable control-panel-health.timer
systemctl start control-panel-health.timer

log_success "Health check timer configured (runs every 5 minutes)"

# Create log rotation configuration
log_info "Setting up log rotation..."
cat > /etc/logrotate.d/control-panel << EOF
/var/log/control-panel-monitor/*.log {
    daily
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 root root
    sharedscripts
    postrotate
        systemctl reload rsyslog > /dev/null 2>&1 || true
    endscript
}

/var/www/control-panel/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    missingok
    sharedscripts
}
EOF

log_success "Log rotation configured"

# Display usage instructions
echo ""
log_info "╔═══════════════════════════════════════════════════════╗"
log_info "║  Monitoring Setup Complete                           ║"
log_info "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "Available monitoring commands:"
echo ""
echo "  control-panel-health-check  - Run health check"
echo "  control-panel-performance   - View performance report"
echo ""
echo "Monitoring status:"
echo "  systemctl status control-panel-health.timer"
echo ""
echo "View health check logs:"
echo "  journalctl -u control-panel-health -f"
echo "  tail -f /var/log/control-panel-monitor/health.log"
echo ""

log_success "Monitoring setup completed successfully!"
