#!/bin/bash
################################################################################
# FirewallD Configuration for Liberu Control Panel
# This script configures FirewallD for RHEL/AlmaLinux/Rocky Linux
################################################################################

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   log_error "This script must be run as root" 
   exit 1
fi

# Check if FirewallD is installed
if ! command -v firewall-cmd &> /dev/null; then
    log_info "FirewallD not found. Installing..."
    dnf install -y firewalld
fi

# Start and enable FirewallD
log_info "Starting FirewallD..."
systemctl start firewalld
systemctl enable firewalld

log_info "Configuring FirewallD for Liberu Control Panel..."

# Set default zone
log_info "Setting default zone to public..."
firewall-cmd --set-default-zone=public

# Allow HTTP and HTTPS
log_info "Allowing HTTP/HTTPS..."
firewall-cmd --permanent --zone=public --add-service=http
firewall-cmd --permanent --zone=public --add-service=https

# Allow SSH
log_info "Allowing SSH..."
firewall-cmd --permanent --zone=public --add-service=ssh

# Configure mail services (if needed)
read -p "Configure mail server? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Allowing mail services..."
    firewall-cmd --permanent --zone=public --add-service=smtp
    firewall-cmd --permanent --zone=public --add-service=smtps
    firewall-cmd --permanent --zone=public --add-service=smtp-submission
    firewall-cmd --permanent --zone=public --add-service=imap
    firewall-cmd --permanent --zone=public --add-service=imaps
    firewall-cmd --permanent --zone=public --add-service=pop3
    firewall-cmd --permanent --zone=public --add-service=pop3s
fi

# Configure DNS (if needed)
read -p "Configure DNS server? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Allowing DNS..."
    firewall-cmd --permanent --zone=public --add-service=dns
fi

# Configure FTP (if needed)
read -p "Configure FTP server? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Allowing FTP..."
    firewall-cmd --permanent --zone=public --add-service=ftp
fi

# Rate limiting using rich rules (prevent brute force SSH)
log_info "Adding rate limiting for SSH..."
firewall-cmd --permanent --zone=public --add-rich-rule='rule service name=ssh limit value=10/m accept'

# Reload firewall to apply changes
log_info "Reloading firewall..."
firewall-cmd --reload

# Show active rules
log_info "Active firewall rules:"
firewall-cmd --list-all

log_info "FirewallD configuration complete!"
log_warn "Make sure you can still access SSH before disconnecting!"
