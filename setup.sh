#!/bin/bash
# Setup script for the control-panel-laravel project.
#
# This script prepares the project environment by copying the .env.example to .env (if necessary),
# installing dependencies, generating application keys, and setting up Docker containers.

clear
echo "=================================="
echo "===== USER: [$(whoami)]"
echo "===== [PHP $(php -r 'echo phpversion();')]"
echo "=================================="
echo ""
echo ""
echo "=================================="
echo "===== PREPARING YOUR PROJECT..."
echo "=================================="
echo ""

# Setup the .env file
copy=true
while $yn; do
    read -p "🎬 DEV ---> DO YOU WANT TO COPY THE .ENV.EXAMPLE TO .ENV? (y/n) " yn
    case $yn in
        [Yy]* ) echo -e "\e[92mCopying .env.example to .env \e[39m"; cp .env.example .env; copy=true; break;;
        [Nn]* ) echo -e "\e[92mContinuing with your .env configuration \e[39m"; copy=false; break;;
        * ) echo "Please answer yes or no."; copy=true; ;;
    esac
done
echo ""
echo "=================================="
echo ""
echo ""

# Ask user to confirm that .env file is properly setup before continuing
if [ "$copy" = true ]; then
    answ=true
    while $cond; do
        read -p "🎬 DEV ---> DID YOU SETUP YOUR ENVIRONMENT VARIABLES IN THE .ENV FILE? (y/n) " cond
        case $cond in
            [Yy]* ) echo -e "\e[92mPerfect let's continue with the setup"; answ=false; break;;
            [Nn]* ) exit;;
            * ) echo "Please answer yes or no."; answ=false; ;;
        esac
    done
fi
echo ""
echo "=================================="
echo ""
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null
then
    echo "Docker is not installed. Please install Docker and try again."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null
then
    echo "Docker Compose is not installed. Please install Docker Compose and try again."
    exit 1
fi

# Build Docker images
echo "🎬 DEV ---> BUILDING DOCKER IMAGES"
docker-compose build
echo ""
echo "=================================="
echo ""
echo ""

# Start Docker containers
echo "🎬 DEV ---> STARTING DOCKER CONTAINERS"
docker-compose up -d
echo ""
echo "=================================="
echo ""
echo ""

# Run database migrations
echo "🎬 DEV ---> RUNNING DATABASE MIGRATIONS"
docker-compose exec control-panel php artisan migrate:fresh
echo ""
echo "=================================="
echo ""
echo ""

# Seed database
echo "🎬 DEV ---> SEEDING DATABASE"
docker-compose exec control-panel php artisan db:seed
echo ""
echo "=================================="
echo ""
echo ""

# Run optimization commands for Laravel
echo "🎬 DEV ---> OPTIMIZING LARAVEL"
docker-compose exec control-panel php artisan optimize:clear
docker-compose exec control-panel php artisan route:clear
echo ""
echo ""
echo "\e[92m==================================\e[39m"
echo "\e[92m============== DONE ==============\e[39m"
echo "\e[92m==================================\e[39m"
echo ""
echo ""

echo "Your control panel is now running in Docker containers."
echo "Access it at: http://localhost"
echo ""
echo "To stop the containers, run: docker-compose down"
