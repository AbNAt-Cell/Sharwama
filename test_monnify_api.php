<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Http\Request;
use Monnify\MonnifyLaravel\Monnify;

echo "🎯 Monnify Sandbox API Test\n";
echo "===========================\n\n";

try {
    echo "1. Testing Monnify API Connection:\n";
    
    // Test by getting transactions list
    $testResponse = Monnify::transactions()->all(['page' => 1, 'size' => 1]);
    
    if ($testResponse) {
        echo "   ✅ API connection successful!\n";
        echo "   Connection test response received\n\n";
        
        echo "2. Testing Payment Initialization:\n";
        
        // Test payment initialization
        $paymentData = [
            'amount' => 5000,
            'customerName' => 'John Doe',
            'customerEmail' => 'johndoe@example.com', 
            'paymentReference' => 'SANDBOX_TEST_' . time(),
            'paymentDescription' => 'Testing Monnify Sandbox API',
            'currencyCode' => 'NGN',
            'contractCode' => config('monnify.contract_code'),
            'redirectUrl' => 'https://your-website.com/callback',
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER']
        ];
        
        $initResponse = Monnify::transactions()->initialise($paymentData);
        
        if ($initResponse) {
            echo "   ✅ Payment initialization successful!\n";
            echo "   Response: " . json_encode($initResponse, JSON_PRETTY_PRINT) . "\n\n";
            
            // Extract transaction reference for verification test
            if (isset($initResponse['responseBody']['transactionReference'])) {
                $transactionRef = $initResponse['responseBody']['transactionReference'];
                
                echo "3. Testing Payment Status Check:\n";
                
                $statusResponse = Monnify::transactions()->status($transactionRef);
                
                if ($statusResponse) {
                    echo "   ✅ Payment status check successful!\n";
                    echo "   Response: " . json_encode($statusResponse, JSON_PRETTY_PRINT) . "\n\n";
                } else {
                    echo "   ❌ Payment status check failed\n";
                }
            }
        } else {
            echo "   ❌ Payment initialization failed\n";
        }
        
    } else {
        echo "   ❌ API connection test failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error occurred: " . $e->getMessage() . "\n";
    echo "Error details: " . $e->getTraceAsString() . "\n";
}

echo "\n🎉 Sandbox Testing Complete!\n";
echo "If all tests passed, your Monnify integration is ready for use.\n";

?>