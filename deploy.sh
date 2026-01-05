#!/bin/bash

# Deployment script for Sharwamahut Laravel Backend
# Run this script after pulling from GitHub

echo "Starting deployment..."

# Pull latest changes
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Generate Passport keys if they don't exist
if [ ! -f "storage/oauth-private.key" ]; then
    echo "Generating Passport encryption keys..."
    php artisan passport:keys --force
    chmod 600 storage/oauth-private.key
    chmod 644 storage/oauth-public.key
fi

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage symlink for images
if [ ! -L "public/storage" ]; then
    echo "Creating storage symlink for images..."
    php artisan storage:link
fi

# Set proper permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "Deployment complete!"
