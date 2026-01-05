#!/bin/bash

# Sharwama Hut - Fix Images Not Showing
# Run this script via cPanel Terminal to fix storage symlink and permissions

echo "=== Fixing Storage Symlink and Permissions ==="
echo ""

# Navigate to application directory
cd /home/egqcvktb/app.sharwamahut.com

# Remove existing symlink if it exists
echo "1. Removing old storage symlink..."
rm -f public/storage

# Create fresh storage symlink
echo "2. Creating new storage symlink..."
php artisan storage:link

# Set proper permissions
echo "3. Setting proper permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod -R 755 public/storage

# Check if symlink was created successfully
echo ""
echo "4. Verifying symlink..."
if [ -L public/storage ]; then
    echo "✓ Storage symlink created successfully!"
    ls -la public/storage
else
    echo "✗ Failed to create storage symlink"
fi

echo ""
echo "=== Done! ==="
echo ""
echo "If images still don't show, check:"
echo "1. Files exist in: storage/app/public/"
echo "2. .env has correct APP_URL"
echo "3. Run: php artisan config:cache"
