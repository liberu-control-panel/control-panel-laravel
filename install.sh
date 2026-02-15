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
# - AlmaLinux / RHEL 8/9
# - Rocky Linux 8/9
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
            almalinux|rhel|rocky)
                if [[ ! "$OS_VERSION" =~ ^[89] ]]; then
                    log_warning "$OS_NAME version $OS_VERSION detected. Supported versions: 8, 9"
                    read -p "Continue anyway? (y/N) " -n 1 -r
                    echo
                    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                        exit 1
                    fi
                fi
                ;;
            *)
                log_error "Unsupported operating system: $OS_NAME"
                log_info "Supported systems: Ubuntu LTS, Debian, AlmaLinux, RHEL, Rocky Linux"
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
    echo -e "${GREEN}4)${NC} ${BOLD}Exit${NC}"
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
        almalinux|rhel|rocky)
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
        almalinux|rhel|rocky)
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

# Install standalone services on RHEL/AlmaLinux
install_standalone_rhel() {
    log_info "Installing services on RHEL/AlmaLinux..."
    
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

# Setup standalone application
setup_standalone_app() {
    log_info "Setting up Control Panel application..."
    
    # Create web directory
    WEB_DIR="/var/www/control-panel"
    mkdir -p "$WEB_DIR"
    
    # Copy application files
    log_info "Copying application files..."
    rsync -av --exclude='.git' --exclude='node_modules' --exclude='vendor' "$SCRIPT_DIR/" "$WEB_DIR/"
    
    # Set permissions
    chown -R www-data:www-data "$WEB_DIR" 2>/dev/null || chown -R nginx:nginx "$WEB_DIR"
    chmod -R 755 "$WEB_DIR"
    chmod -R 775 "$WEB_DIR/storage"
    chmod -R 775 "$WEB_DIR/bootstrap/cache"
    
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
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
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
        
        read -p "Enter your choice [1-4]: " choice
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
                log_info "Installation cancelled"
                exit 0
                ;;
            *)
                log_error "Invalid choice. Please select 1-4."
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
