#!/usr/bin/env php
<?php

/*
 * Storage Link Creator
 * Creates a symlink from public/storage to storage/app/public
 */

$basePath = __DIR__;
$publicStoragePath = $basePath . '/public/storage';
$storageAppPublicPath = $basePath . '/storage/app/public';

echo "Creating storage symlink...\n\n";

// Check if symlink already exists
if (file_exists($publicStoragePath)) {
    if (is_link($publicStoragePath)) {
        echo "✓ Storage symlink already exists!\n";
        exit(0);
    } else {
        echo "⚠ Warning: public/storage exists but is not a symlink.\n";
        echo "Please delete it manually and run this script again.\n";
        exit(1);
    }
}

// Create the symlink
if (symlink($storageAppPublicPath, $publicStoragePath)) {
    echo "✓ Storage symlink created successfully!\n";
    echo "Images should now be accessible.\n";
    echo "\nYou can now delete this script for security.\n";
} else {
    echo "✗ Failed to create symlink.\n";
    echo "You may need to run: php artisan storage:link\n";
    exit(1);
}
