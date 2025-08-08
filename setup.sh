#!/bin/bash

# Camagru Setup Script
# This script sets up the Camagru project for development

echo "🎥 Setting up Camagru project..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "📄 Creating .env file..."
    cp .env.example .env
    echo "✅ .env file created. Please edit it with your configuration."
fi

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p uploads logs
chmod 755 uploads logs

# Generate filter images
echo "🎨 Generating filter images..."
php scripts/generate_filters.php

# Start Docker containers
echo "🐳 Starting Docker containers..."
docker-compose up -d

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Check if containers are running
if docker-compose ps | grep -q "Up"; then
    echo "✅ Docker containers are running!"
    echo ""
    echo "🌟 Camagru is ready!"
    echo "📱 Application: http://localhost:8080"
    echo "🗄️  phpMyAdmin: http://localhost:8081"
    echo ""
    echo "📚 Next steps:"
    echo "1. Open http://localhost:8080 in your browser"
    echo "2. Register a new account"
    echo "3. Start creating amazing photos!"
    echo ""
    echo "🔧 Development commands:"
    echo "  - View logs: docker-compose logs -f"
    echo "  - Stop containers: docker-compose down"
    echo "  - Restart containers: docker-compose restart"
else
    echo "❌ Failed to start Docker containers. Please check the logs:"
    echo "docker-compose logs"
fi
