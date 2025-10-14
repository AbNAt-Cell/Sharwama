<?php
// Simple cURL test for Monnify integration

echo "🚀 Testing Monnify Sandbox with cURL\n";
echo "====================================\n\n";

function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['body' => $response, 'code' => $httpCode];
}

// Test 1: Health Check
echo "1. Testing Health Endpoint:\n";
$healthResponse = makeRequest('http://localhost:8000/api/v1/health');
echo "   Status Code: " . $healthResponse['code'] . "\n";
if ($healthResponse['code'] == 200) {
    echo "   ✅ API is accessible\n";
} else {
    echo "   ❌ API not accessible\n";
    echo "   Response: " . $healthResponse['body'] . "\n";
}
echo "\n";

// Test 2: Monnify Config Test
echo "2. Testing Monnify Configuration:\n";
$configResponse = makeRequest('http://localhost:8000/api/v1/monnify/test-connection');
echo "   Status Code: " . $configResponse['code'] . "\n";
echo "   Response: " . $configResponse['body'] . "\n\n";

// Test 3: Payment Initialization
echo "3. Testing Payment Initialization:\n";
$paymentData = [
    'amount' => 1500.00,
    'customer_name' => 'John Doe',
    'customer_email' => 'johndoe@example.com',
    'customer_phone' => '+2348123456789',
    'payment_description' => 'Test payment from sandbox',
    'redirect_url' => 'https://example.com/callback'
];

$initResponse = makeRequest('http://localhost:8000/api/v1/monnify/initialize', 'POST', $paymentData);
echo "   Status Code: " . $initResponse['code'] . "\n";
echo "   Response: " . $initResponse['body'] . "\n\n";

// Test 4: Payment Verification (with dummy transaction reference)
echo "4. Testing Payment Verification:\n";
$verifyData = [
    'transaction_reference' => 'MNFY|20250125|000001'
];

$verifyResponse = makeRequest('http://localhost:8000/api/v1/monnify/verify', 'POST', $verifyData);
echo "   Status Code: " . $verifyResponse['code'] . "\n";
echo "   Response: " . $verifyResponse['body'] . "\n\n";

echo "🎯 Testing Complete!\n";
echo "If you see JSON responses above, your API endpoints are working.\n";
echo "The actual Monnify API integration will depend on your credentials and network access.\n";

?>