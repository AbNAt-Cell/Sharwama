#!/usr/bin/env php
<?php

/*
 * Passport Key Generator Script
 * This script generates OAuth2 encryption keys for Laravel Passport
 */

$basePath = __DIR__;
$storagePath = $basePath . '/storage';

// Check if we're in the right directory
if (!file_exists($basePath . '/artisan')) {
    die("Error: Please place this script in your Laravel application root directory.\n");
}

echo "Starting Passport key generation...\n\n";

// Generate private key
$privateKey = openssl_pkey_new([
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);

// Export private key
openssl_pkey_export($privateKey, $privateKeyString);

// Get public key
$publicKeyDetails = openssl_pkey_get_details($privateKey);
$publicKeyString = $publicKeyDetails['key'];

// Save private key
$privateKeyPath = $storagePath . '/oauth-private.key';
file_put_contents($privateKeyPath, $privateKeyString);
chmod($privateKeyPath, 0600);
echo "✓ Private key generated: oauth-private.key\n";

// Save public key
$publicKeyPath = $storagePath . '/oauth-public.key';
file_put_contents($publicKeyPath, $publicKeyString);
chmod($publicKeyPath, 0644);
echo "✓ Public key generated: oauth-public.key\n";

echo "\n✓ Passport encryption keys generated successfully!\n";
echo "You can now delete this script for security.\n";
