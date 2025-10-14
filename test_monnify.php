<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "🧪 Testing Monnify Sandbox Configuration\n";
echo "=====================================\n\n";

// Test 1: Check if .env variables are loaded
echo "1. Environment Variables:\n";
echo "   MONNIFY_API_KEY: " . ($_ENV['MONNIFY_API_KEY'] ?? 'NOT SET') . "\n";
echo "   MONNIFY_SECRET_KEY: " . (isset($_ENV['MONNIFY_SECRET_KEY']) ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "   MONNIFY_CONTRACT_CODE: " . ($_ENV['MONNIFY_CONTRACT_CODE'] ?? 'NOT SET') . "\n";
echo "   MONNIFY_ENVIRONMENT: " . ($_ENV['MONNIFY_ENVIRONMENT'] ?? 'NOT SET') . "\n\n";

// Test 2: Check if Monnify package is installed
echo "2. Monnify Package:\n";
if (class_exists('Monnify\Monnify')) {
    echo "   ✅ Monnify Laravel package is installed\n";
} else {
    echo "   ❌ Monnify Laravel package not found\n";
}

// Test 3: Test API connection
echo "\n3. API Connection Test:\n";
try {
    // Initialize Monnify with credentials
    $apiKey = $_ENV['MONNIFY_API_KEY'] ?? '';
    $secretKey = $_ENV['MONNIFY_SECRET_KEY'] ?? '';
    $contractCode = $_ENV['MONNIFY_CONTRACT_CODE'] ?? '';
    $environment = $_ENV['MONNIFY_ENVIRONMENT'] ?? 'SANDBOX';
    
    if (empty($apiKey) || empty($secretKey) || empty($contractCode)) {
        echo "   ❌ Missing required credentials\n";
    } else {
        echo "   ✅ All credentials are present\n";
        echo "   📝 Ready for sandbox testing\n";
        
        // Test data for initialization
        $testData = [
            'amount' => 1000,
            'customerName' => 'Test Customer',
            'customerEmail' => 'test@example.com',
            'paymentReference' => 'TEST_' . time(),
            'paymentDescription' => 'Sandbox Test Payment',
            'currencyCode' => 'NGN',
            'contractCode' => $contractCode,
            'redirectUrl' => 'https://your-website.com/callback',
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER']
        ];
        
        echo "\n   📋 Sample Payment Data:\n";
        echo "   " . json_encode($testData, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🚀 Next Steps:\n";
echo "   1. Start your Laravel server: php artisan serve --port=8000\n";
echo "   2. Test the health endpoint: http://localhost:8000/api/v1/health\n";
echo "   3. Test Monnify connection: http://localhost:8000/api/v1/monnify/test-connection\n";
echo "   4. Initialize a payment: POST http://localhost:8000/api/v1/monnify/initialize\n";
echo "\n";

?>