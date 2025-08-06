#!/bin/bash

# Railway Deployment Script for Laravel PostgreSQL Migration
# Run this after deploying to Railway with PostgreSQL addon

echo "🚀 Starting Railway PostgreSQL Migration..."

# Set error handling
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're on Railway
if [ -z "$RAILWAY_ENVIRONMENT" ]; then
    echo -e "${RED}❌ This script should be run on Railway environment${NC}"
    exit 1
fi

# Check PostgreSQL environment variables
if [ -z "$PGHOST" ] || [ -z "$PGDATABASE" ] || [ -z "$PGUSER" ] || [ -z "$PGPASSWORD" ]; then
    echo -e "${RED}❌ PostgreSQL environment variables not found${NC}"
    echo "Make sure Railway PostgreSQL addon is properly connected"
    exit 1
fi

echo -e "${GREEN}✓ Railway environment detected${NC}"
echo -e "${GREEN}✓ PostgreSQL variables found${NC}"

# Cache configuration
echo -e "${YELLOW}📋 Caching Laravel configuration...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo -e "${YELLOW}🗄️  Running database migrations...${NC}"
php artisan migrate --force

# Check if production data seeder exists and run it
if [ -f "database/seeders/ProductionDataSeeder.php" ] && [ -f "postgresql-export.json" ]; then
    echo -e "${YELLOW}🌱 Running production data seeder...${NC}"
    php artisan db:seed --class=ProductionDataSeeder --force
    echo -e "${GREEN}✓ Production data seeded successfully${NC}"
else
    echo -e "${YELLOW}⚠️  Production data seeder or export data not found${NC}"
    echo "   Run the MySQL migration script locally first"
fi

# Optimize for production
echo -e "${YELLOW}⚡ Optimizing for production...${NC}"
php artisan optimize

# Set proper storage permissions (if needed)
echo -e "${YELLOW}🔒 Setting storage permissions...${NC}"
chmod -R 755 storage
chmod -R 755 bootstrap/cache

echo -e "${GREEN}✅ Railway deployment completed successfully!${NC}"
echo ""
echo "Your Laravel application is now running on Railway with PostgreSQL!"
echo "Database: $PGDATABASE"
echo "Host: $PGHOST"