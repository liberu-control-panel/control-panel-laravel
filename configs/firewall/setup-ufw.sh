#!/bin/bash
################################################################################
# UFW Firewall Configuration for Liberu Control Panel
# This script configures UFW (Uncomplicated Firewall) for standalone deployment
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

# Check if UFW is installed
if ! command -v ufw &> /dev/null; then
    log_info "UFW not found. Installing..."
    apt-get update
    apt-get install -y ufw
fi

log_info "Configuring UFW firewall for Liberu Control Panel..."

# Reset UFW to default
log_info "Resetting UFW to default settings..."
ufw --force reset

# Set default policies
log_info "Setting default policies..."
ufw default deny incoming
ufw default allow outgoing

# Allow SSH (critical - do this first!)
log_info "Allowing SSH..."
ufw allow 22/tcp comment 'SSH'

# Allow HTTP and HTTPS
log_info "Allowing HTTP/HTTPS..."
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

# Allow mail services (if configured)
read -p "Configure mail server ports? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Allowing mail server ports..."
    ufw allow 25/tcp comment 'SMTP'
    ufw allow 587/tcp comment 'SMTP Submission'
    ufw allow 465/tcp comment 'SMTPS'
    ufw allow 143/tcp comment 'IMAP'
    ufw allow 993/tcp comment 'IMAPS'
    ufw allow 110/tcp comment 'POP3'
    ufw allow 995/tcp comment 'POP3S'
fi

# Allow DNS (if configured)
read -p "Configure DNS server? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Allowing DNS..."
    ufw allow 53/tcp comment 'DNS TCP'
    ufw allow 53/udp comment 'DNS UDP'
fi

# Allow FTP (if needed)
read -p "Configure FTP server? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Allowing FTP..."
    ufw allow 21/tcp comment 'FTP'
    ufw allow 20/tcp comment 'FTP Data'
    # Passive FTP range
    ufw allow 49152:65534/tcp comment 'FTP Passive'
fi

# Rate limiting for SSH (prevent brute force)
log_info "Enabling rate limiting for SSH..."
ufw limit 22/tcp comment 'SSH Rate Limit'

# Enable logging
log_info "Enabling firewall logging..."
ufw logging medium

# Enable UFW
log_info "Enabling UFW firewall..."
ufw --force enable

# Show status
log_info "UFW Status:"
ufw status verbose

log_info "Firewall configuration complete!"
log_warn "Make sure you can still access SSH before disconnecting!"
