#!/bin/bash

################################################################################
# Liberu Control Panel - Unified Installation Script
# 
# This script provides a unified installation interface for:
# - Kubernetes deployment (recommended for production)
# - Docker Compose deployment (for development/small-scale)
# - Standalone deployment (traditional server setup)
# 
# Supported Operating Systems:
# - Ubuntu LTS (20.04, 22.04, 24.04)
# - Debian (11, 12)
# - AlmaLinux / RHEL 8/9/10
# - Rocky Linux 8/9/10
# - CloudLinux 8/9/10 (Standalone only)
################################################################################

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Logging functions
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

log_header() {
    echo ""
    echo -e "${CYAN}${BOLD}=========================================${NC}"
    echo -e "${CYAN}${BOLD}$1${NC}"
    echo -e "${CYAN}${BOLD}=========================================${NC}"
    echo ""
}

# Display banner
display_banner() {
    clear
    echo -e "${MAGENTA}${BOLD}"
    cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║     Liberu Control Panel - Installation Wizard               ║
║                                                               ║
║     A modern web hosting control panel for:                  ║
║     - Virtual Hosts (NGINX)                                   ║
║     - DNS Management (BIND/PowerDNS)                          ║
║     - Mail Services (Postfix/Dovecot)                         ║
║     - Database Management (MySQL/PostgreSQL)                  ║
║     - Docker/Kubernetes Orchestration                         ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
    echo ""
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

# Detect operating system
detect_os() {
    log_info "Detecting operating system..."
    
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
        OS_NAME=$NAME
        
        case $OS in
            ubuntu)
                if [[ ! "$OS_VERSION" =~ ^(20\.04|22\.04|24\.04) ]]; then
                    log_warning "Ubuntu version $OS_VERSION detected. Supported versions: 20.04, 22.04, 24.04"
                    read -p "Continue anyway? (y/N) " -n 1 -r
                    echo
                    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                        exit 1
                    fi
                fi
                ;;
            debian)
                if [[ ! "$OS_VERSION" =~ ^(11|12) ]]; then
                    log_warning "Debian version $OS_VERSION detected. Supported versions: 11 (Bullseye), 12 (Bookworm)"
                    read -p "Continue anyway? (y/N) " -n 1 -r
                    echo
                    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                        exit 1
                    fi
                fi
                ;;
            almalinux|rhel|rocky|cloudlinux)
                if [[ ! "$OS_VERSION" =~ ^[8-9]|^10 ]]; then
                    log_warning "$OS_NAME version $OS_VERSION detected. Supported versions: 8, 9, 10"
                    read -p "Continue anyway? (y/N) " -n 1 -r
                    echo
                    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                        exit 1
                    fi
                fi
                ;;
            *)
                log_error "Unsupported operating system: $OS_NAME"
                log_info "Supported systems: Ubuntu LTS, Debian, AlmaLinux, RHEL, Rocky Linux, CloudLinux"
                exit 1
                ;;
        esac
        
        log_success "Detected: $OS_NAME $OS_VERSION"
    else
        log_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi
}

# Display installation menu
display_menu() {
    log_header "Installation Method Selection"
    
    echo -e "${BOLD}Choose your installation method:${NC}"
    echo ""
    echo -e "${GREEN}1)${NC} ${BOLD}Kubernetes${NC} (Recommended for Production)"
    echo "   - Full container orchestration"
    echo "   - Auto-scaling and load balancing"
    echo "   - High availability and self-healing"
    echo "   - Supports: Self-managed, AWS EKS, Azure AKS, GCP GKE, DigitalOcean"
    echo ""
    echo -e "${GREEN}2)${NC} ${BOLD}Docker Compose${NC} (Development/Small-Scale)"
    echo "   - Container-based deployment"
    echo "   - Easy local development"
    echo "   - Simple multi-container setup"
    echo "   - Best for single-server deployments"
    echo ""
    echo -e "${GREEN}3)${NC} ${BOLD}Standalone${NC} (Traditional Server)"
    echo "   - Direct server installation"
    echo "   - Traditional NGINX/Apache setup"
    echo "   - Standard Linux services"
    echo "   - No container overhead"
    echo ""
    echo -e "${GREEN}4)${NC} ${BOLD}Standalone DNS Only${NC} (DNS Cluster Node)"
    echo "   - DNS server only installation"
    echo "   - BIND9 or PowerDNS"
    echo "   - Ready for DNS cluster"
    echo "   - Lightweight nameserver setup"
    echo ""
    echo -e "${GREEN}5)${NC} ${BOLD}Exit${NC}"
    echo ""
}

# Install prerequisites for all methods
install_common_prerequisites() {
    log_info "Installing common prerequisites..."
    
    case $OS in
        ubuntu)
            apt-get update
            apt-get install -y \
                curl \
                wget \
                git \
                ca-certificates \
                gnupg \
                lsb-release \
                software-properties-common \
                apt-transport-https
            ;;
        almalinux|rhel|rocky|cloudlinux)
            dnf install -y \
                curl \
                wget \
                git \
                ca-certificates \
                gnupg \
                yum-utils
            ;;
    esac
    
    log_success "Common prerequisites installed"
}

# Kubernetes installation
install_kubernetes() {
    log_header "Kubernetes Installation"
    
    echo "This will install:"
    echo "  - Kubernetes cluster (if not using managed K8s)"
    echo "  - NGINX Ingress Controller"
    echo "  - cert-manager for SSL certificates"
    echo "  - Control Panel with all services"
    echo "  - MariaDB cluster"
    echo "  - Redis cache"
    echo "  - Mail services (Postfix/Dovecot)"
    echo "  - DNS cluster (PowerDNS)"
    echo "  - PHP multi-version support"
    echo ""
    
    read -p "Continue with Kubernetes installation? (Y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        return
    fi
    
    # Check if install-k8s.sh exists
    if [[ ! -f "$SCRIPT_DIR/install-k8s.sh" ]]; then
        log_error "install-k8s.sh not found in $SCRIPT_DIR"
        exit 1
    fi
    
    # Make scripts executable
    chmod +x "$SCRIPT_DIR/install-k8s.sh"
    chmod +x "$SCRIPT_DIR/install-control-panel.sh"
    
    # Run Kubernetes installation
    log_info "Running Kubernetes cluster setup..."
    "$SCRIPT_DIR/install-k8s.sh"
    
    # Check if K8s installation succeeded
    if [[ $? -eq 0 ]]; then
        log_success "Kubernetes cluster setup completed"
        
        # Ask if user wants to install control panel now
        echo ""
        read -p "Install Control Panel and services now? (Y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Nn]$ ]]; then
            log_info "Running Control Panel installation..."
            "$SCRIPT_DIR/install-control-panel.sh"
            
            if [[ $? -eq 0 ]]; then
                log_success "Control Panel installation completed!"
                display_kubernetes_next_steps
            else
                log_error "Control Panel installation failed. Check logs above."
                exit 1
            fi
        else
            log_info "You can install the Control Panel later by running:"
            log_info "  sudo ./install-control-panel.sh"
        fi
    else
        log_error "Kubernetes installation failed. Check logs above."
        exit 1
    fi
}

# Docker Compose installation
install_docker() {
    log_header "Docker Compose Installation"
    
    echo "This will install:"
    echo "  - Docker Engine"
    echo "  - Docker Compose"
    echo "  - Control Panel application"
    echo "  - MariaDB/PostgreSQL database"
    echo "  - NGINX reverse proxy"
    echo "  - Mail services (Postfix/Dovecot)"
    echo "  - DNS server (BIND9)"
    echo "  - Let's Encrypt SSL automation"
    echo ""
    
    read -p "Continue with Docker Compose installation? (Y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        return
    fi
    
    # Install Docker
    install_docker_engine
    
    # Check if setup.sh exists
    if [[ ! -f "$SCRIPT_DIR/setup.sh" ]]; then
        log_error "setup.sh not found in $SCRIPT_DIR"
        exit 1
    fi
    
    # Check if docker-compose.yml exists
    if [[ ! -f "$SCRIPT_DIR/docker-compose.yml" ]]; then
        log_error "docker-compose.yml not found in $SCRIPT_DIR"
        exit 1
    fi
    
    # Create secrets directory and files if they don't exist
    setup_docker_secrets
    
    # Setup .env file
    setup_env_file
    
    # Run setup script
    log_info "Running Docker Compose setup..."
    chmod +x "$SCRIPT_DIR/setup.sh"
    
    # Run setup with default answers for automation
    cd "$SCRIPT_DIR"
    
    log_info "Building and starting Docker containers..."
    docker-compose build
    docker-compose up -d
    
    log_success "Docker Compose setup completed!"
    display_docker_next_steps
}

# Install Docker Engine
install_docker_engine() {
    log_info "Installing Docker Engine..."
    
    # Check if Docker is already installed
    if command -v docker &> /dev/null; then
        log_success "Docker is already installed: $(docker --version)"
        return
    fi
    
    case $OS in
        ubuntu)
            # Add Docker's official GPG key
            install -m 0755 -d /etc/apt/keyrings
            curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
            chmod a+r /etc/apt/keyrings/docker.gpg
            
            # Set up the repository
            echo \
              "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
              $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
            
            # Install Docker Engine
            apt-get update
            apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
        debian)
            # Add Docker's official GPG key
            install -m 0755 -d /etc/apt/keyrings
            curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
            chmod a+r /etc/apt/keyrings/docker.gpg
            
            # Set up the repository
            echo \
              "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
              $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
            
            # Install Docker Engine
            apt-get update
            apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
        almalinux|rhel|rocky)
            # Add Docker repository
            dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
            
            # Install Docker Engine
            dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
    esac
    
    # Start and enable Docker
    systemctl start docker
    systemctl enable docker
    
    log_success "Docker Engine installed successfully"
}

# Setup Docker secrets
setup_docker_secrets() {
    log_info "Setting up Docker secrets..."
    
    mkdir -p "$SCRIPT_DIR/secrets"
    
    # Generate database root password if not exists
    if [[ ! -f "$SCRIPT_DIR/secrets/db_root_password.txt" ]]; then
        openssl rand -base64 32 > "$SCRIPT_DIR/secrets/db_root_password.txt"
        log_success "Generated database root password"
    fi
    
    # Generate database password if not exists
    if [[ ! -f "$SCRIPT_DIR/secrets/db_password.txt" ]]; then
        openssl rand -base64 32 > "$SCRIPT_DIR/secrets/db_password.txt"
        log_success "Generated database password"
    fi
    
    chmod 600 "$SCRIPT_DIR/secrets"/*.txt
}

# Setup .env file
setup_env_file() {
    log_info "Setting up environment file..."
    
    if [[ ! -f "$SCRIPT_DIR/.env" ]]; then
        if [[ -f "$SCRIPT_DIR/.env.example" ]]; then
            cp "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.env"
            
            # Generate APP_KEY
            if command -v php &> /dev/null; then
                cd "$SCRIPT_DIR"
                # Try to generate key using artisan
                if [[ -f "artisan" ]]; then
                    APP_KEY=$(php artisan key:generate --show 2>/dev/null || echo "base64:$(openssl rand -base64 32)")
                else
                    APP_KEY="base64:$(openssl rand -base64 32)"
                fi
            else
                APP_KEY="base64:$(openssl rand -base64 32)"
            fi
            
            # Update .env with generated key
            sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" "$SCRIPT_DIR/.env"
            
            log_success "Created .env file from .env.example"
        else
            log_error ".env.example not found"
            exit 1
        fi
    else
        log_info ".env file already exists"
    fi
    
    # Prompt for domain name
    echo ""
    read -p "Enter your domain name (e.g., control.example.com): " DOMAIN
    DOMAIN=${DOMAIN:-localhost}
    
    read -p "Enter your email for Let's Encrypt (e.g., admin@example.com): " EMAIL
    EMAIL=${EMAIL:-admin@example.com}
    
    # Update .env file
    sed -i "s|CONTROL_PANEL_DOMAIN=.*|CONTROL_PANEL_DOMAIN=$DOMAIN|" "$SCRIPT_DIR/.env"
    sed -i "s|LETSENCRYPT_EMAIL=.*|LETSENCRYPT_EMAIL=$EMAIL|" "$SCRIPT_DIR/.env"
    
    log_success "Environment file configured"
}

# Standalone installation
install_standalone() {
    log_header "Standalone Installation"
    
    echo "This will install directly on your server:"
    echo "  - NGINX web server"
    echo "  - PHP-FPM (latest version)"
    echo "  - MariaDB/MySQL database"
    echo "  - Redis cache"
    echo "  - Postfix mail server"
    echo "  - Dovecot IMAP/POP3 server"
    echo "  - BIND9 DNS server"
    echo "  - Control Panel application"
    echo "  - Certbot for SSL certificates"
    echo ""
    
    read -p "Continue with standalone installation? (Y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        return
    fi
    
    # Install services based on OS
    case $OS in
        ubuntu|debian)
            install_standalone_ubuntu
            ;;
        almalinux|rhel|rocky|cloudlinux)
            install_standalone_rhel
            ;;
    esac
    
    # Setup the control panel application
    setup_standalone_app
    
    log_success "Standalone installation completed!"
    display_standalone_next_steps
}

# Install standalone services on Ubuntu
install_standalone_ubuntu() {
    log_info "Installing services on Ubuntu/Debian..."
    
    # Add PHP repository
    if [[ "$OS" == "ubuntu" ]]; then
        add-apt-repository -y ppa:ondrej/php
    elif [[ "$OS" == "debian" ]]; then
        # Add Sury PHP repository for Debian
        apt-get install -y lsb-release apt-transport-https ca-certificates wget
        wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
    fi
    apt-get update
    
    # Install NGINX
    log_info "Installing NGINX..."
    apt-get install -y nginx
    
    # Install PHP and extensions
    log_info "Installing PHP 8.3 and extensions..."
    apt-get install -y \
        php8.3-fpm \
        php8.3-cli \
        php8.3-common \
        php8.3-mysql \
        php8.3-zip \
        php8.3-gd \
        php8.3-mbstring \
        php8.3-curl \
        php8.3-xml \
        php8.3-bcmath \
        php8.3-redis \
        php8.3-intl
    
    # Install MariaDB
    log_info "Installing MariaDB..."
    apt-get install -y mariadb-server mariadb-client
    
    # Install Redis
    log_info "Installing Redis..."
    apt-get install -y redis-server
    
    # Install Composer
    log_info "Installing Composer..."
    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
    
    # Install Node.js and npm
    log_info "Installing Node.js..."
    if ! command -v node &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs
    fi
    
    # Install Certbot
    log_info "Installing Certbot..."
    apt-get install -y certbot python3-certbot-nginx
    
    # Install mail services
    log_info "Installing mail services..."
    apt-get install -y postfix dovecot-core dovecot-imapd dovecot-pop3d
    
    # Install BIND9
    log_info "Installing BIND9..."
    apt-get install -y bind9 bind9utils bind9-doc
    
    # Start and enable services
    systemctl start nginx
    systemctl enable nginx
    systemctl start php8.3-fpm
    systemctl enable php8.3-fpm
    systemctl start mariadb
    systemctl enable mariadb
    systemctl start redis-server
    systemctl enable redis-server
    
    log_success "All services installed and started"
}

# Install standalone services on RHEL/AlmaLinux/Rocky/CloudLinux
install_standalone_rhel() {
    log_info "Installing services on RHEL/AlmaLinux/Rocky/CloudLinux..."
    
    # Enable EPEL repository
    dnf install -y epel-release
    
    # Add Remi repository for PHP
    dnf install -y https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %rhel).rpm
    dnf module reset php -y
    dnf module enable php:remi-8.3 -y
    
    # Install NGINX
    log_info "Installing NGINX..."
    dnf install -y nginx
    
    # Install PHP and extensions
    log_info "Installing PHP 8.3 and extensions..."
    dnf install -y \
        php \
        php-fpm \
        php-cli \
        php-common \
        php-mysqlnd \
        php-zip \
        php-gd \
        php-mbstring \
        php-curl \
        php-xml \
        php-bcmath \
        php-redis \
        php-intl
    
    # Install MariaDB
    log_info "Installing MariaDB..."
    dnf install -y mariadb-server mariadb
    
    # Install Redis
    log_info "Installing Redis..."
    dnf install -y redis
    
    # Install Composer
    log_info "Installing Composer..."
    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
    
    # Install Node.js
    log_info "Installing Node.js..."
    if ! command -v node &> /dev/null; then
        dnf module install -y nodejs:20
    fi
    
    # Install Certbot
    log_info "Installing Certbot..."
    dnf install -y certbot python3-certbot-nginx
    
    # Install mail services
    log_info "Installing mail services..."
    dnf install -y postfix dovecot
    
    # Install BIND
    log_info "Installing BIND..."
    dnf install -y bind bind-utils
    
    # Configure SELinux for web services
    setsebool -P httpd_can_network_connect 1
    setsebool -P httpd_can_network_connect_db 1
    
    # Start and enable services
    systemctl start nginx
    systemctl enable nginx
    systemctl start php-fpm
    systemctl enable php-fpm
    systemctl start mariadb
    systemctl enable mariadb
    systemctl start redis
    systemctl enable redis
    
    log_success "All services installed and started"
}

# Create the dedicated Control Panel service account
# This is the ONLY account that receives sudo privileges.
# Virtual host site users run PHP-FPM as their own accounts but have NO sudo.
create_control_panel_service_user() {
    log_info "Creating Control Panel service account (cp-panel)..."

    local CP_SERVICE_USER="cp-panel"

    if ! id "$CP_SERVICE_USER" &>/dev/null; then
        useradd --system --no-create-home --shell /usr/sbin/nologin \
            --comment "Liberu Control Panel Service Account" \
            "$CP_SERVICE_USER"
        log_success "Created service account: $CP_SERVICE_USER"
    else
        log_info "Service account '$CP_SERVICE_USER' already exists"
    fi

    # Add the service account to the web server group so it can read web files
    case $OS in
        ubuntu|debian)
            usermod -aG www-data "$CP_SERVICE_USER"
            ;;
        almalinux|rhel|rocky|cloudlinux)
            usermod -aG nginx "$CP_SERVICE_USER"
            ;;
    esac

    echo "$CP_SERVICE_USER"
}

# Create a dedicated PHP-FPM pool for the Control Panel service account
# so the panel's own PHP code runs as cp-panel (which has sudo) rather
# than as the shared www-data/nginx user.
create_control_panel_php_fpm_pool() {
    local CP_SERVICE_USER="cp-panel"
    local PHP_VERSION="8.3"

    log_info "Creating PHP-FPM pool for Control Panel service account..."

    # Determine pool.d directory based on OS
    local POOL_DIR
    case $OS in
        ubuntu|debian)
            POOL_DIR="/etc/php/${PHP_VERSION}/fpm/pool.d"
            ;;
        *)
            POOL_DIR="/etc/php-fpm.d"
            ;;
    esac

    local POOL_FILE="${POOL_DIR}/${CP_SERVICE_USER}.conf"
    local SOCKET_PATH="/run/php/php${PHP_VERSION}-fpm-${CP_SERVICE_USER}.sock"

    # The socket must be readable by the nginx worker process user
    local WEB_SERVER_USER="www-data"
    case $OS in
        almalinux|rhel|rocky|cloudlinux)
            WEB_SERVER_USER="nginx"
            ;;
    esac

    cat > "$POOL_FILE" << EOF
; PHP-FPM pool for the Liberu Control Panel service account
; This pool runs the control panel application as $CP_SERVICE_USER so that
; sudo commands issued by the control panel are executed under that account.
[$CP_SERVICE_USER]
user = $CP_SERVICE_USER
group = $CP_SERVICE_USER
listen = $SOCKET_PATH
listen.owner = $WEB_SERVER_USER
listen.group = $WEB_SERVER_USER
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
EOF

    chmod 644 "$POOL_FILE"

    # Reload PHP-FPM to activate the pool
    case $OS in
        ubuntu|debian)
            systemctl reload "php${PHP_VERSION}-fpm" || true
            ;;
        *)
            systemctl reload php-fpm || true
            ;;
    esac

    log_success "PHP-FPM pool created at $POOL_FILE (socket: $SOCKET_PATH)"

    # Return the socket path for use in the nginx config
    echo "$SOCKET_PATH"
}

# Setup sudo access for the web server user
setup_sudo_access() {
    log_info "Configuring sudo access for Control Panel user..."

    # The dedicated service account is the ONLY account that receives sudo.
    # The shared web server user (www-data/nginx) and per-site user accounts
    # do NOT receive any sudo privileges.
    local CP_USER="cp-panel"

    local SUDOERS_FILE="/etc/sudoers.d/control-panel"

    # Write a targeted sudoers file granting ONLY the cp-panel service account
    # passwordless sudo access to the system commands used by the Control Panel.
    # The shared web server user (www-data/nginx) and per-site user accounts
    # deliberately receive NO sudo privileges.
    cat > "$SUDOERS_FILE" << EOF
# Liberu Control Panel - passwordless sudo for $CP_USER
# Generated by install.sh on $(date -u '+%Y-%m-%dT%H:%M:%SZ')
#
# IMPORTANT: Only the $CP_USER service account receives these privileges.
# Per-site system users (created for each virtual host) and the shared
# www-data/nginx user do NOT appear in this file and have no sudo access.

# Service management - restricted to the services managed by Control Panel
$CP_USER ALL=(root) NOPASSWD: /bin/systemctl reload nginx, /bin/systemctl restart nginx, /bin/systemctl status nginx
$CP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload nginx, /usr/bin/systemctl restart nginx, /usr/bin/systemctl status nginx
$CP_USER ALL=(root) NOPASSWD: /bin/systemctl reload php*, /bin/systemctl restart php*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload php*, /usr/bin/systemctl restart php*
$CP_USER ALL=(root) NOPASSWD: /bin/systemctl reload named, /bin/systemctl restart named, /bin/systemctl reload bind9, /bin/systemctl restart bind9
$CP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload named, /usr/bin/systemctl restart named, /usr/bin/systemctl reload bind9, /usr/bin/systemctl restart bind9
$CP_USER ALL=(root) NOPASSWD: /bin/systemctl reload postfix, /bin/systemctl restart postfix, /bin/systemctl reload dovecot, /bin/systemctl restart dovecot
$CP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload postfix, /usr/bin/systemctl restart postfix, /usr/bin/systemctl reload dovecot, /usr/bin/systemctl restart dovecot
$CP_USER ALL=(root) NOPASSWD: /bin/systemctl reload mariadb, /bin/systemctl restart mariadb, /bin/systemctl reload mysql, /bin/systemctl restart mysql
$CP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload mariadb, /usr/bin/systemctl restart mariadb, /usr/bin/systemctl reload mysql, /usr/bin/systemctl restart mysql
$CP_USER ALL=(root) NOPASSWD: /bin/systemctl reload redis, /bin/systemctl restart redis, /bin/systemctl reload redis-server, /bin/systemctl restart redis-server
$CP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload redis, /usr/bin/systemctl restart redis, /usr/bin/systemctl reload redis-server, /usr/bin/systemctl restart redis-server

# NGINX management
$CP_USER ALL=(root) NOPASSWD: /usr/sbin/nginx -t
$CP_USER ALL=(root) NOPASSWD: /usr/bin/nginx -t

# File operations restricted to directories managed by Control Panel
$CP_USER ALL=(root) NOPASSWD: /bin/mv /tmp/* /etc/nginx/sites-available/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mv /tmp/* /etc/nginx/sites-available/*
$CP_USER ALL=(root) NOPASSWD: /bin/chmod 644 /etc/nginx/sites-available/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chmod 644 /etc/nginx/sites-available/*
$CP_USER ALL=(root) NOPASSWD: /bin/ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled/*
$CP_USER ALL=(root) NOPASSWD: /bin/rm /etc/nginx/sites-available/*, /bin/rm /etc/nginx/sites-enabled/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/rm /etc/nginx/sites-available/*, /usr/bin/rm /etc/nginx/sites-enabled/*
$CP_USER ALL=(root) NOPASSWD: /bin/mkdir -p /var/www/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mkdir -p /var/www/*
$CP_USER ALL=(root) NOPASSWD: /bin/chmod 755 /var/www/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chmod 755 /var/www/*
# chown is restricted: the target user must match the cp-user-* naming pattern
# used for per-site accounts, preventing ownership changes to root or system users
$CP_USER ALL=(root) NOPASSWD: /bin/chown -R cp-user-* /var/www/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chown -R cp-user-* /var/www/*
$CP_USER ALL=(root) NOPASSWD: /bin/chown -R cp-user-*\:cp-user-* /var/www/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chown -R cp-user-*\:cp-user-* /var/www/*

# PHP-FPM per-user pool management (for virtual host isolation)
$CP_USER ALL=(root) NOPASSWD: /bin/mv /tmp/* /etc/php/*/fpm/pool.d/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mv /tmp/* /etc/php/*/fpm/pool.d/*
$CP_USER ALL=(root) NOPASSWD: /bin/mv /tmp/* /etc/php-fpm.d/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mv /tmp/* /etc/php-fpm.d/*
$CP_USER ALL=(root) NOPASSWD: /bin/chmod 644 /etc/php/*/fpm/pool.d/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chmod 644 /etc/php/*/fpm/pool.d/*
$CP_USER ALL=(root) NOPASSWD: /bin/chmod 644 /etc/php-fpm.d/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chmod 644 /etc/php-fpm.d/*
$CP_USER ALL=(root) NOPASSWD: /bin/rm -f /etc/php/*/fpm/pool.d/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/rm -f /etc/php/*/fpm/pool.d/*
$CP_USER ALL=(root) NOPASSWD: /bin/rm -f /etc/php-fpm.d/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/rm -f /etc/php-fpm.d/*

# Per-site user account management: only no-login accounts matching the cp-user-* pattern
# are permitted, preventing creation of privileged or arbitrary system users
$CP_USER ALL=(root) NOPASSWD: /usr/sbin/useradd --no-create-home --shell /usr/sbin/nologin cp-user-*
$CP_USER ALL=(root) NOPASSWD: /usr/sbin/useradd -M -s /usr/sbin/nologin cp-user-*

# DNS zone file management
$CP_USER ALL=(root) NOPASSWD: /bin/mkdir -p /etc/bind/zones
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mkdir -p /etc/bind/zones
$CP_USER ALL=(root) NOPASSWD: /bin/mv /tmp/* /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mv /tmp/* /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /bin/chown bind\:bind /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chown bind\:bind /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /bin/chmod 644 /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chmod 644 /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /bin/rm -f /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/rm -f /etc/bind/zones/*
$CP_USER ALL=(root) NOPASSWD: /bin/sed -i * /etc/bind/named.conf*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/sed -i * /etc/bind/named.conf*
$CP_USER ALL=(root) NOPASSWD: /bin/bash -c echo * >> /etc/bind/named.conf*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/bash -c echo * >> /etc/bind/named.conf*

# DNS validation
$CP_USER ALL=(root) NOPASSWD: /usr/sbin/named-checkconf
$CP_USER ALL=(root) NOPASSWD: /usr/sbin/named-checkzone

# SSL certificate management
$CP_USER ALL=(root) NOPASSWD: /usr/bin/certbot

# Mail service management
$CP_USER ALL=(root) NOPASSWD: /usr/sbin/postmap /etc/postfix/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/postmap /etc/postfix/*
$CP_USER ALL=(root) NOPASSWD: /bin/mkdir -p /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/mkdir -p /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /bin/chown -R vmail\:vmail /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chown -R vmail\:vmail /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /bin/chmod -R 700 /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/chmod -R 700 /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /bin/rm -rf /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/rm -rf /var/mail/*
$CP_USER ALL=(root) NOPASSWD: /bin/cp /etc/postfix/* /etc/postfix/*.bak
$CP_USER ALL=(root) NOPASSWD: /usr/bin/cp /etc/postfix/* /etc/postfix/*.bak
$CP_USER ALL=(root) NOPASSWD: /bin/sed -i * /etc/postfix/*
$CP_USER ALL=(root) NOPASSWD: /usr/bin/sed -i * /etc/postfix/*

# PostgreSQL management
$CP_USER ALL=(postgres) NOPASSWD: /usr/bin/psql
$CP_USER ALL=(postgres) NOPASSWD: /usr/bin/createdb
$CP_USER ALL=(postgres) NOPASSWD: /usr/bin/dropdb
EOF

    # Restrict permissions on the sudoers file (required by sudo)
    chmod 440 "$SUDOERS_FILE"

    # Validate the sudoers file syntax before leaving it in place
    if visudo -c -f "$SUDOERS_FILE" &>/dev/null; then
        log_success "sudo access configured for '$CP_USER' (sudoers: $SUDOERS_FILE)"
    else
        log_error "Generated sudoers file failed validation - removing to avoid lockout"
        rm -f "$SUDOERS_FILE"
        return 1
    fi
}

# Setup standalone application
setup_standalone_app() {
    log_info "Setting up Control Panel application..."
    
    # Create web directory
    WEB_DIR="/var/www/control-panel"
    mkdir -p "$WEB_DIR"
    
    # Copy application files
    log_info "Copying application files..."
    rsync -av --exclude='.git' --exclude='node_modules' --exclude='vendor' "$SCRIPT_DIR/" "$WEB_DIR/"
    
    # Create the dedicated Control Panel service account and its PHP-FPM pool.
    # This is done before setting file permissions so we can use it as the owner.
    local CP_SERVICE_USER
    CP_SERVICE_USER=$(create_control_panel_service_user)

    # Set permissions - owned by the service account, readable by the web server group
    chown -R "$CP_SERVICE_USER:$CP_SERVICE_USER" "$WEB_DIR"
    chmod -R 755 "$WEB_DIR"
    chmod -R 775 "$WEB_DIR/storage"
    chmod -R 775 "$WEB_DIR/bootstrap/cache"

    # Create the dedicated PHP-FPM pool that runs the control panel as cp-panel
    local CP_FPM_SOCKET
    CP_FPM_SOCKET=$(create_control_panel_php_fpm_pool)

    # Grant the cp-panel service account the sudo access it needs to manage
    # system services. Per-site users and www-data/nginx do NOT get sudo.
    setup_sudo_access

    # Setup .env file
    if [[ ! -f "$WEB_DIR/.env" ]]; then
        cp "$WEB_DIR/.env.example" "$WEB_DIR/.env"
        
        # Generate APP_KEY
        cd "$WEB_DIR"
        php artisan key:generate
    fi
    
    # Install Composer dependencies
    log_info "Installing Composer dependencies..."
    cd "$WEB_DIR"
    composer install --no-dev --optimize-autoloader
    
    # Install NPM dependencies and build assets
    log_info "Building frontend assets..."
    npm install
    npm run build
    
    # Setup database
    setup_standalone_database
    
    # Run migrations
    log_info "Running database migrations..."
    php artisan migrate --force
    
    # Seed database
    read -p "Seed database with sample data? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        php artisan db:seed
    fi
    
    # Configure NGINX
    setup_nginx_config
    
    log_success "Application setup completed"
}

# Setup standalone database
setup_standalone_database() {
    log_info "Configuring database..."
    
    # Generate random password
    DB_PASSWORD=$(openssl rand -base64 24)
    
    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS control_panel;"
    mysql -e "CREATE USER IF NOT EXISTS 'control_panel'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
    mysql -e "GRANT ALL PRIVILEGES ON control_panel.* TO 'control_panel'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Update .env file
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=control_panel|" "$WEB_DIR/.env"
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=control_panel|" "$WEB_DIR/.env"
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" "$WEB_DIR/.env"
    
    log_success "Database configured"
    log_info "Database credentials saved to $WEB_DIR/.env"
}

# Setup NGINX configuration
setup_nginx_config() {
    log_info "Configuring NGINX..."
    
    # Ask for domain name
    echo ""
    read -p "Enter your domain name (or press Enter for localhost): " DOMAIN
    DOMAIN=${DOMAIN:-localhost}
    
    # Create NGINX config
    cat > /etc/nginx/sites-available/control-panel << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root /var/www/control-panel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm-cp-panel.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    # Enable site
    if [[ -d /etc/nginx/sites-enabled ]]; then
        ln -sf /etc/nginx/sites-available/control-panel /etc/nginx/sites-enabled/
        rm -f /etc/nginx/sites-enabled/default
    else
        # RHEL/AlmaLinux doesn't use sites-enabled by default
        ln -sf /etc/nginx/sites-available/control-panel /etc/nginx/conf.d/control-panel.conf
    fi
    
    # Test NGINX config
    nginx -t
    
    # Reload NGINX
    systemctl reload nginx
    
    log_success "NGINX configured for $DOMAIN"
    
    # Offer to setup SSL
    if [[ "$DOMAIN" != "localhost" ]]; then
        echo ""
        read -p "Setup Let's Encrypt SSL certificate for $DOMAIN? (y/N) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            read -p "Enter your email address: " EMAIL
            certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL"
        fi
    fi
}

# Standalone DNS Only installation
install_dns_only() {
    log_header "Standalone DNS Only Installation"
    
    echo "This will install DNS server only for DNS cluster:"
    echo "  - BIND9 DNS server (default)"
    echo "  - Or PowerDNS (alternative)"
    echo "  - DNS cluster configuration (master/slave)"
    echo "  - Nameserver hostname setup"
    echo "  - Minimal system resources"
    echo ""
    echo "This installation is perfect for:"
    echo "  - DNS cluster nodes"
    echo "  - Distributed nameserver infrastructure"
    echo "  - Secondary/tertiary DNS servers"
    echo ""
    
    read -p "Continue with standalone DNS only installation? (Y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        return
    fi
    
    # Ask for DNS server type
    echo ""
    echo "Select DNS server software:"
    echo "1) BIND9 (recommended, stable, widely used)"
    echo "2) PowerDNS (modern, API-enabled, high performance)"
    echo ""
    read -p "Enter your choice [1-2] (default: 1): " DNS_SERVER_CHOICE
    DNS_SERVER_CHOICE=${DNS_SERVER_CHOICE:-1}
    
    # Ask for nameserver hostname
    echo ""
    read -p "Enter nameserver hostname (e.g., ns1.example.com): " NAMESERVER_HOSTNAME
    while [[ -z "$NAMESERVER_HOSTNAME" ]]; do
        log_error "Nameserver hostname is required!"
        read -p "Enter nameserver hostname (e.g., ns1.example.com): " NAMESERVER_HOSTNAME
    done
    
    # Ask for DNS cluster role
    echo ""
    echo "Select DNS cluster role:"
    echo "1) Master (primary DNS server)"
    echo "2) Slave (secondary DNS server)"
    echo "3) Both (master and slave)"
    echo ""
    read -p "Enter your choice [1-3] (default: 3): " DNS_ROLE_CHOICE
    DNS_ROLE_CHOICE=${DNS_ROLE_CHOICE:-3}
    
    case $DNS_ROLE_CHOICE in
        1)
            DNS_MASTER="yes"
            DNS_SLAVE="no"
            ;;
        2)
            DNS_MASTER="no"
            DNS_SLAVE="yes"
            ;;
        *)
            DNS_MASTER="yes"
            DNS_SLAVE="yes"
            ;;
    esac
    
    # Ask for allowed AXFR IPs for zone transfers
    echo ""
    echo "Configure zone transfer (AXFR) access:"
    echo "For security, restrict zone transfers to specific IPs"
    echo "Examples: 192.168.1.10, 10.0.0.0/24, 203.0.113.5;203.0.113.6"
    echo ""
    read -p "Enter allowed IPs for zone transfers (leave empty for localhost only): " AXFR_IPS
    if [[ -z "$AXFR_IPS" ]]; then
        AXFR_IPS="127.0.0.1;::1"
        log_info "Using secure default: localhost only (127.0.0.1;::1)"
    fi
    
    # Install DNS server based on OS
    case $OS in
        ubuntu|debian)
            install_dns_only_ubuntu "$DNS_SERVER_CHOICE"
            ;;
        almalinux|rhel|rocky|cloudlinux)
            install_dns_only_rhel "$DNS_SERVER_CHOICE"
            ;;
    esac
    
    # Configure DNS server
    configure_dns_server "$DNS_SERVER_CHOICE"
    
    log_success "Standalone DNS only installation completed!"
    display_dns_only_next_steps
}

# Install DNS only on Ubuntu/Debian
install_dns_only_ubuntu() {
    local dns_choice=$1
    log_info "Installing DNS server on Ubuntu/Debian..."
    
    apt-get update
    
    if [[ "$dns_choice" == "2" ]]; then
        # Install PowerDNS
        log_info "Installing PowerDNS..."
        apt-get install -y pdns-server pdns-backend-mysql mariadb-server
        
        # Secure MariaDB installation
        systemctl start mariadb
        systemctl enable mariadb
        
        # Create PowerDNS database
        PDNS_DB_PASSWORD=$(openssl rand -base64 24)
        
        log_info "Configuring PowerDNS database..."
        mysql -e "CREATE DATABASE IF NOT EXISTS powerdns;"
        mysql -e "CREATE USER IF NOT EXISTS 'powerdns'@'localhost' IDENTIFIED BY '$PDNS_DB_PASSWORD';"
        mysql -e "GRANT ALL PRIVILEGES ON powerdns.* TO 'powerdns'@'localhost';"
        mysql -e "FLUSH PRIVILEGES;"
        
        # Import PowerDNS schema
        local schema_file="/usr/share/doc/pdns-backend-mysql/schema.mysql.sql"
        if [[ -f "$schema_file" ]]; then
            mysql powerdns < "$schema_file"
            log_success "PowerDNS schema imported successfully"
        else
            log_warning "PowerDNS schema file not found at $schema_file - you may need to import it manually"
        fi
        
        log_success "PowerDNS installed with MySQL backend"
    else
        # Install BIND9 (default)
        log_info "Installing BIND9..."
        apt-get install -y bind9 bind9utils bind9-doc dnsutils
        
        log_success "BIND9 installed"
    fi
}

# Install DNS only on RHEL/AlmaLinux/Rocky/CloudLinux
install_dns_only_rhel() {
    local dns_choice=$1
    log_info "Installing DNS server on RHEL/AlmaLinux/Rocky/CloudLinux..."
    
    # Enable EPEL repository
    dnf install -y epel-release
    
    if [[ "$dns_choice" == "2" ]]; then
        # Install PowerDNS
        log_info "Installing PowerDNS..."
        dnf install -y pdns pdns-backend-mysql mariadb-server
        
        # Secure MariaDB installation
        systemctl start mariadb
        systemctl enable mariadb
        
        # Create PowerDNS database
        PDNS_DB_PASSWORD=$(openssl rand -base64 24)
        
        log_info "Configuring PowerDNS database..."
        mysql -e "CREATE DATABASE IF NOT EXISTS powerdns;"
        mysql -e "CREATE USER IF NOT EXISTS 'powerdns'@'localhost' IDENTIFIED BY '$PDNS_DB_PASSWORD';"
        mysql -e "GRANT ALL PRIVILEGES ON powerdns.* TO 'powerdns'@'localhost';"
        mysql -e "FLUSH PRIVILEGES;"
        
        # Import PowerDNS schema
        local schema_file="/usr/share/doc/pdns/schema.mysql.sql"
        if [[ -f "$schema_file" ]]; then
            mysql powerdns < "$schema_file"
            log_success "PowerDNS schema imported successfully"
        else
            log_warning "PowerDNS schema file not found at $schema_file - you may need to import it manually"
        fi
        
        log_success "PowerDNS installed with MySQL backend"
    else
        # Install BIND (default)
        log_info "Installing BIND..."
        dnf install -y bind bind-utils
        
        # Configure SELinux for BIND
        setsebool -P named_write_master_zones 1
        
        log_success "BIND installed"
    fi
}

# Configure DNS server
configure_dns_server() {
    local dns_choice=$1
    log_info "Configuring DNS server..."
    
    if [[ "$dns_choice" == "2" ]]; then
        # Configure PowerDNS
        configure_powerdns
    else
        # Configure BIND9
        configure_bind9
    fi
}

# Configure PowerDNS
configure_powerdns() {
    log_info "Configuring PowerDNS..."
    
    # Backup original config
    if [[ -f /etc/pdns/pdns.conf ]]; then
        cp /etc/pdns/pdns.conf /etc/pdns/pdns.conf.backup
    elif [[ -f /etc/powerdns/pdns.conf ]]; then
        cp /etc/powerdns/pdns.conf /etc/powerdns/pdns.conf.backup
    fi
    
    # Determine config file location
    PDNS_CONF="/etc/pdns/pdns.conf"
    if [[ ! -f "$PDNS_CONF" ]] && [[ -f "/etc/powerdns/pdns.conf" ]]; then
        PDNS_CONF="/etc/powerdns/pdns.conf"
    fi
    
    # Generate API key
    PDNS_API_KEY=$(openssl rand -hex 32)
    
    # Get server IP for restricted API access
    SERVER_IP=$(hostname -I | awk '{print $1}')
    
    # Determine PowerDNS user/group based on OS
    if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
        PDNS_USER="pdns"
        PDNS_GROUP="pdns"
    else
        # RHEL-based systems
        PDNS_USER="pdns"
        PDNS_GROUP="pdns"
    fi
    
    # Create PowerDNS configuration
    cat > "$PDNS_CONF" << EOF
# PowerDNS Configuration for DNS Cluster
# Nameserver: $NAMESERVER_HOSTNAME

# Database backend
launch=gmysql
gmysql-host=localhost
gmysql-port=3306
gmysql-dbname=powerdns
gmysql-user=powerdns
gmysql-password=$PDNS_DB_PASSWORD

# API configuration (for management)
api=yes
api-key=$PDNS_API_KEY
webserver=yes
webserver-address=127.0.0.1
webserver-port=8081
webserver-allow-from=127.0.0.1,$SERVER_IP

# Cluster configuration
master=$DNS_MASTER
slave=$DNS_SLAVE
allow-axfr-ips=$AXFR_IPS

# Performance tuning
max-tcp-connections=100
query-cache-ttl=20
cache-ttl=20

# Security
setuid=$PDNS_USER
setgid=$PDNS_GROUP
EOF
    
    # Save credentials securely
    echo "$PDNS_DB_PASSWORD" > /root/pdns-db-password.txt
    echo "$PDNS_API_KEY" > /root/pdns-api-key.txt
    chmod 600 /root/pdns-db-password.txt /root/pdns-api-key.txt
    
    # Secure the PowerDNS config file
    chmod 640 "$PDNS_CONF"
    if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
        chown root:pdns "$PDNS_CONF" 2>/dev/null || true
    else
        chown root:pdns "$PDNS_CONF" 2>/dev/null || chown root:root "$PDNS_CONF"
    fi
    
    # Start and enable PowerDNS
    PDNS_SERVICE=""
    if systemctl list-units --type=service | grep -q "pdns.service"; then
        PDNS_SERVICE="pdns"
        systemctl start pdns
        systemctl enable pdns
    elif systemctl list-units --type=service | grep -q "pdns-server.service"; then
        PDNS_SERVICE="pdns-server"
        systemctl start pdns-server
        systemctl enable pdns-server
    fi
    
    log_success "PowerDNS configured for DNS cluster"
    log_info "PowerDNS API available at: http://127.0.0.1:8081 (localhost only)"
    log_info "API credentials saved to /root/pdns-api-key.txt and /root/pdns-db-password.txt"
}

# Configure BIND9
configure_bind9() {
    log_info "Configuring BIND9..."
    
    # Determine BIND config directory and paths based on OS
    if [[ -d /etc/bind ]]; then
        # Debian/Ubuntu
        BIND_DIR="/etc/bind"
        NAMED_CONF="$BIND_DIR/named.conf"
        ZONES_DIR="$BIND_DIR/zones"
        ROOT_HINTS="/usr/share/dns/root.hints"
        DB_LOCAL="$BIND_DIR/db.local"
        DB_127="$BIND_DIR/db.127"
        DB_0="$BIND_DIR/db.0"
        DB_255="$BIND_DIR/db.255"
        BIND_USER="bind"
        BIND_GROUP="bind"
    else
        # RHEL/AlmaLinux/Rocky/CloudLinux
        BIND_DIR="/etc"
        NAMED_CONF="$BIND_DIR/named.conf"
        ZONES_DIR="/var/named"
        ROOT_HINTS="/var/named/named.ca"
        DB_LOCAL="/var/named/named.localhost"
        DB_127="/var/named/named.loopback"
        DB_0="/var/named/named.empty"
        DB_255="/var/named/named.empty"
        BIND_USER="named"
        BIND_GROUP="named"
    fi
    
    # Backup original config
    if [[ -f "$NAMED_CONF" ]]; then
        cp "$NAMED_CONF" "$NAMED_CONF.backup"
    fi
    
    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')
    
    # Create zones directory
    mkdir -p "$ZONES_DIR"
    
    # Create named.conf.options
    cat > "$BIND_DIR/named.conf.options" << EOF
// BIND9 Configuration for DNS Cluster
// Nameserver: $NAMESERVER_HOSTNAME

options {
    directory "$ZONES_DIR";
    
    // Listen on all interfaces
    listen-on { any; };
    listen-on-v6 { any; };
    
    // Allow queries from any source
    allow-query { any; };
    
    // AXFR configuration for cluster
    allow-transfer { $AXFR_IPS; };
    
    // Recursion disabled for authoritative server
    recursion no;
    
    // DNSSEC validation
    dnssec-validation auto;
    
    // Performance tuning
    tcp-clients 100;
    max-cache-size 256M;
};
EOF
    
    # Create main named.conf
    cat > "$NAMED_CONF" << EOF
// BIND9 Main Configuration
// Nameserver: $NAMESERVER_HOSTNAME
// Role: Master=$DNS_MASTER, Slave=$DNS_SLAVE

include "$BIND_DIR/named.conf.options";
include "$BIND_DIR/named.conf.local";

// Default zones
include "$BIND_DIR/named.conf.default-zones";
EOF
    
    # Create named.conf.local for zone definitions
    cat > "$BIND_DIR/named.conf.local" << EOF
// Local zone definitions
// Add your zone configurations here
// Example:
// zone "example.com" {
//     type master;
//     file "$ZONES_DIR/db.example.com";
//     allow-transfer { $AXFR_IPS; };
// };
EOF
    
    # Create default zones if not exists
    if [[ ! -f "$BIND_DIR/named.conf.default-zones" ]]; then
        cat > "$BIND_DIR/named.conf.default-zones" << EOF
// Default zones
zone "." {
    type hint;
    file "$ROOT_HINTS";
};

zone "localhost" {
    type master;
    file "$DB_LOCAL";
};

zone "127.in-addr.arpa" {
    type master;
    file "$DB_127";
};

zone "0.in-addr.arpa" {
    type master;
    file "$DB_0";
};

zone "255.in-addr.arpa" {
    type master;
    file "$DB_255";
};
EOF
    fi
    
    # Set proper permissions
    chown -R $BIND_USER:$BIND_GROUP "$ZONES_DIR"
    chmod 755 "$ZONES_DIR"
    
    # Test configuration
    if command -v named-checkconf &> /dev/null; then
        if named-checkconf "$NAMED_CONF"; then
            log_success "BIND9 configuration is valid"
        else
            log_error "BIND9 configuration has errors"
            return 1
        fi
    fi
    
    # Start and enable BIND9
    if systemctl list-units --type=service | grep -q "bind9.service"; then
        systemctl start bind9
        systemctl enable bind9
    elif systemctl list-units --type=service | grep -q "named.service"; then
        systemctl start named
        systemctl enable named
    fi
    
    log_success "BIND9 configured for DNS cluster"
}

# Display next steps for DNS Only
display_dns_only_next_steps() {
    echo ""
    log_header "DNS Server Installation Complete!"
    
    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')
    
    echo -e "${GREEN}✓${NC} DNS server is installed and running"
    echo -e "${GREEN}✓${NC} Nameserver hostname: $NAMESERVER_HOSTNAME"
    echo -e "${GREEN}✓${NC} Cluster role - Master: $DNS_MASTER, Slave: $DNS_SLAVE"
    echo ""
    echo -e "${BOLD}Server Information:${NC}"
    echo "  IP Address: $SERVER_IP"
    echo "  Hostname: $(hostname)"
    echo "  Nameserver: $NAMESERVER_HOSTNAME"
    echo ""
    echo -e "${BOLD}Next Steps:${NC}"
    echo ""
    
    if [[ "$DNS_SERVER_CHOICE" == "2" ]]; then
        echo "1. PowerDNS API is available at:"
        echo "   http://127.0.0.1:8081 (localhost only for security)"
        echo ""
        echo "2. API and database credentials saved to:"
        echo "   - API Key: /root/pdns-api-key.txt"
        echo "   - DB Password: /root/pdns-db-password.txt"
        echo ""
        echo "3. To access API remotely, use SSH tunnel:"
        echo "   ssh -L 8081:localhost:8081 user@$SERVER_IP"
        echo ""
        echo "4. Add DNS zones via PowerDNS API or database"
        echo ""
        echo "5. Configure your domain registrar to use:"
        echo "   Nameserver: $NAMESERVER_HOSTNAME"
        echo "   IP: $(hostname -I | awk '{print $1}')"
        echo ""
        echo "6. For cluster setup, configure zone transfers:"
        echo "   - Primary server: Add AXFR allowed IPs in config"
        echo "   - Secondary server: Configure as slave"
        echo ""
        echo "7. Check PowerDNS status:"
        if systemctl list-units --type=service | grep -q "pdns.service"; then
            echo "   systemctl status pdns"
        elif systemctl list-units --type=service | grep -q "pdns-server.service"; then
            echo "   systemctl status pdns-server"
        else
            echo "   systemctl status pdns"
        fi
        echo ""
        echo "8. View PowerDNS logs:"
        if systemctl list-units --type=service | grep -q "pdns.service"; then
            echo "   journalctl -u pdns -f"
        elif systemctl list-units --type=service | grep -q "pdns-server.service"; then
            echo "   journalctl -u pdns-server -f"
        else
            echo "   journalctl -u pdns -f"
        fi
    else
        # Get proper zones directory for current OS
        if [[ -d /etc/bind ]]; then
            ZONES_DIR="/etc/bind/zones"
            CONFIG_DIR="/etc/bind"
        else
            ZONES_DIR="/var/named"
            CONFIG_DIR="/etc"
        fi
        
        echo "1. Configure DNS zones in:"
        echo "   $CONFIG_DIR/named.conf.local"
        echo "   $ZONES_DIR/"
        echo ""
        echo "2. Add a zone example:"
        echo "   zone \"example.com\" {"
        echo "       type master;"
        echo "       file \"$ZONES_DIR/db.example.com\";"
        echo "       allow-transfer { $AXFR_IPS; };"
        echo "   };"
        echo ""
        echo "3. Configure your domain registrar to use:"
        echo "   Nameserver: $NAMESERVER_HOSTNAME"
        echo "   IP: $SERVER_IP"
        echo ""
        echo "4. For cluster setup:"
        echo "   - Primary server: Create zones as master"
        echo "   - Secondary server: Create zones as slave"
        echo ""
        echo "5. Reload BIND9 after changes:"
        if systemctl list-units --type=service | grep -q "bind9.service"; then
            echo "   systemctl reload bind9"
        else
            echo "   systemctl reload named"
        fi
        echo ""
        echo "6. Check BIND9 status:"
        if systemctl list-units --type=service | grep -q "bind9.service"; then
            echo "   systemctl status bind9"
        else
            echo "   systemctl status named"
        fi
        echo ""
        echo "7. Test DNS resolution:"
        echo "   dig @localhost example.com"
        echo "   nslookup example.com localhost"
    fi
    echo ""
    echo -e "${YELLOW}Firewall Configuration:${NC}"
    echo "  Make sure port 53 (UDP/TCP) is open:"
    echo "  - UFW: sudo ufw allow 53"
    echo "  - Firewalld: sudo firewall-cmd --add-service=dns --permanent"
    echo ""
    if [[ "$DNS_SERVER_CHOICE" == "2" ]]; then
        echo -e "${YELLOW}Note:${NC} PowerDNS API is bound to localhost (127.0.0.1) for security."
        echo "  To access remotely, use SSH tunneling (see step 3 above)."
        echo "  DO NOT open port 8081 to the internet unless you have specific security measures in place."
        echo ""
    fi
    echo -e "${YELLOW}Documentation:${NC}"
    echo "  - BIND9: https://www.isc.org/bind/"
    echo "  - PowerDNS: https://doc.powerdns.com/"
    echo "  - DNS Cluster Setup: See repository documentation"
    echo ""
}

# Display next steps for Kubernetes
display_kubernetes_next_steps() {
    echo ""
    log_header "Installation Complete!"
    
    echo -e "${GREEN}✓${NC} Kubernetes cluster is ready"
    echo -e "${GREEN}✓${NC} Control Panel is deployed"
    echo ""
    echo -e "${BOLD}Next Steps:${NC}"
    echo ""
    echo "1. Get the Ingress IP address:"
    echo "   kubectl get service -n ingress-nginx ingress-nginx-controller"
    echo ""
    echo "2. Point your domain DNS to the Ingress IP"
    echo ""
    echo "3. Access your Control Panel:"
    echo "   https://your-domain.com"
    echo ""
    echo "4. View pod status:"
    echo "   kubectl get pods -n control-panel"
    echo ""
    echo "5. View application logs:"
    echo "   make logs"
    echo ""
    echo -e "${YELLOW}Documentation:${NC}"
    echo "  - Kubernetes Guide: docs/KUBERNETES_INSTALLATION.md"
    echo "  - Managed K8s Setup: docs/MANAGED_KUBERNETES_SETUP.md"
    echo "  - S3 Storage: docs/S3_STORAGE.md"
    echo ""
}

# Display next steps for Docker
display_docker_next_steps() {
    echo ""
    log_header "Installation Complete!"
    
    echo -e "${GREEN}✓${NC} Docker containers are running"
    echo -e "${GREEN}✓${NC} Control Panel is deployed"
    echo ""
    echo -e "${BOLD}Next Steps:${NC}"
    echo ""
    echo "1. Check container status:"
    echo "   docker-compose ps"
    echo ""
    echo "2. View application logs:"
    echo "   docker-compose logs -f control-panel"
    echo ""
    echo "3. Access your Control Panel:"
    if [[ -f "$SCRIPT_DIR/.env" ]]; then
        DOMAIN=$(grep CONTROL_PANEL_DOMAIN "$SCRIPT_DIR/.env" | cut -d '=' -f2)
        echo "   http://$DOMAIN"
    else
        echo "   http://localhost"
    fi
    echo ""
    echo "4. Create admin user:"
    echo "   docker-compose exec control-panel php artisan make:filament-user"
    echo ""
    echo "5. Stop containers:"
    echo "   docker-compose down"
    echo ""
    echo -e "${YELLOW}Credentials:${NC}"
    echo "  Database Root Password: $(cat "$SCRIPT_DIR/secrets/db_root_password.txt" 2>/dev/null || echo 'See secrets/db_root_password.txt')"
    echo "  Database Password: $(cat "$SCRIPT_DIR/secrets/db_password.txt" 2>/dev/null || echo 'See secrets/db_password.txt')"
    echo ""
}

# Display next steps for Standalone
display_standalone_next_steps() {
    echo ""
    log_header "Installation Complete!"
    
    echo -e "${GREEN}✓${NC} All services are installed and running"
    echo -e "${GREEN}✓${NC} Control Panel is deployed"
    echo ""
    echo -e "${BOLD}Next Steps:${NC}"
    echo ""
    echo "1. Access your Control Panel:"
    if [[ -f /etc/nginx/sites-available/control-panel ]]; then
        DOMAIN=$(grep server_name /etc/nginx/sites-available/control-panel | awk '{print $2}' | sed 's/;//')
        echo "   http://$DOMAIN"
    else
        echo "   http://localhost"
    fi
    echo ""
    echo "2. Create admin user:"
    echo "   cd /var/www/control-panel"
    echo "   php artisan make:filament-user"
    echo ""
    echo "3. View logs:"
    echo "   tail -f /var/www/control-panel/storage/logs/laravel.log"
    echo ""
    echo "4. Manage services:"
    echo "   systemctl status nginx"
    echo "   systemctl status php8.3-fpm"
    echo "   systemctl status mariadb"
    echo ""
    echo -e "${YELLOW}Configuration Files:${NC}"
    echo "  Application: /var/www/control-panel"
    echo "  NGINX Config: /etc/nginx/sites-available/control-panel"
    echo "  Environment: /var/www/control-panel/.env"
    echo ""
}

# Main installation flow
main() {
    display_banner
    
    # Check prerequisites
    check_root
    detect_os
    install_common_prerequisites
    
    # Main menu loop
    while true; do
        display_menu
        
        read -p "Enter your choice [1-5]: " choice
        echo ""
        
        case $choice in
            1)
                install_kubernetes
                break
                ;;
            2)
                install_docker
                break
                ;;
            3)
                install_standalone
                break
                ;;
            4)
                install_dns_only
                break
                ;;
            5)
                log_info "Installation cancelled"
                exit 0
                ;;
            *)
                log_error "Invalid choice. Please select 1-5."
                echo ""
                ;;
        esac
    done
    
    echo ""
    log_success "Thank you for installing Liberu Control Panel!"
    echo ""
    echo -e "${CYAN}Support: https://github.com/liberu-control-panel/control-panel-laravel${NC}"
    echo -e "${CYAN}Website: https://liberu.co.uk${NC}"
    echo ""
}

# Run main function
main "$@"
