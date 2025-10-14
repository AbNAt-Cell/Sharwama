<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Monnify\MonnifyLaravel\Facades\Monnify;

class PaymentController extends Controller
{
    /**
     * Initialize payment with dummy data
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function initializePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'string|max:3',
            'payment_method' => 'string|max:50',
            'customer_email' => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        // Generate dummy payment data
        $paymentData = [
            'status' => 'success',
            'message' => 'Payment initialized successfully',
            'data' => [
                'payment_id' => 'pay_' . uniqid(),
                'transaction_id' => 'txn_' . time() . rand(1000, 9999),
                'amount' => floatval($request->input('amount', 100.00)),
                'currency' => $request->input('currency', 'USD'),
                'payment_method' => $request->input('payment_method', 'dummy_gateway'),
                'status' => 'pending',
                'customer' => [
                    'email' => $request->input('customer_email', 'customer@example.com'),
                    'name' => 'John Doe',
                    'phone' => '+1234567890'
                ],
                'payment_url' => 'https://dummy-payment-gateway.com/pay?token=' . bin2hex(random_bytes(16)),
                'expires_at' => now()->addMinutes(30)->toISOString(),
                'created_at' => now()->toISOString()
            ],
            'instructions' => [
                'redirect_url' => 'https://dummy-payment-gateway.com/pay',
                'callback_url' => url('/api/v1/payment/callback'),
                'success_url' => url('/api/v1/payment/success'),
                'cancel_url' => url('/api/v1/payment/cancel')
            ]
        ];

        return response()->json($paymentData, 200);
    }

    /**
     * Get payment status with dummy data
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPaymentStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment ID is required',
                'errors' => $validator->errors()
            ], 400);
        }

        // Generate random status for demo
        $statuses = ['pending', 'success', 'failed', 'cancelled'];
        $randomStatus = $statuses[array_rand($statuses)];

        return response()->json([
            'status' => 'success',
            'data' => [
                'payment_id' => $request->payment_id,
                'transaction_id' => 'txn_' . time() . rand(1000, 9999),
                'status' => $randomStatus,
                'amount' => 100.00,
                'currency' => 'USD',
                'payment_method' => 'dummy_gateway',
                'processed_at' => $randomStatus === 'success' ? now()->toISOString() : null,
                'failure_reason' => $randomStatus === 'failed' ? 'Insufficient funds' : null
            ]
        ], 200);
    }

    /**
     * Payment callback handler (dummy)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentCallback(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment callback received',
            'data' => [
                'payment_id' => $request->input('payment_id', 'pay_' . uniqid()),
                'status' => 'completed',
                'timestamp' => now()->toISOString()
            ]
        ], 200);
    }

    /**
     * Payment success handler (dummy)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentSuccess(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment completed successfully',
            'data' => [
                'payment_id' => $request->input('payment_id', 'pay_' . uniqid()),
                'status' => 'success',
                'amount' => 100.00,
                'currency' => 'USD',
                'completed_at' => now()->toISOString()
            ]
        ], 200);
    }

    /**
     * Payment cancellation handler (dummy)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentCancel(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'cancelled',
            'message' => 'Payment was cancelled by user',
            'data' => [
                'payment_id' => $request->input('payment_id', 'pay_' . uniqid()),
                'status' => 'cancelled',
                'cancelled_at' => now()->toISOString()
            ]
        ], 200);
    }

    /**
     * Monnify initialize payment - Sandbox Mode
     */
    public function monnifyInitialize(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'string|max:20',
            'payment_reference' => 'string|max:50',
            'payment_description' => 'string|max:255',
            'redirect_url' => 'url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Prepare payment data
            $paymentReference = $request->input('payment_reference', 'PAY_REF_' . time() . rand(1000, 9999));
            $paymentData = [
                'amount' => $request->input('amount'),
                'customerName' => $request->input('customer_name'),
                'customerEmail' => $request->input('customer_email'),
                'paymentReference' => $paymentReference,
                'paymentDescription' => $request->input('payment_description', 'Payment'),
                'currencyCode' => $request->input('currency_code', 'NGN'),
                'contractCode' => config('monnify.contract_code'),
                'redirectUrl' => $request->input('redirect_url', url('/api/v1/monnify/callback')),
                'paymentMethods' => $request->input('payment_methods', ["CARD", "ACCOUNT_TRANSFER"])
            ];
            if ($request->has('customer_phone')) {
                $paymentData['customerPhoneNumber'] = $request->input('customer_phone');
            }
            // Always use real Monnify API
            try {
                $response = Monnify::transactions()->initialise($paymentData);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Monnify payment initialized',
                    'monnify_response' => $response
                ], 200);
            } catch (\Exception $monnifyError) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Monnify API error',
                    'error' => $monnifyError->getMessage(),
                    'note' => 'This error is from the actual Monnify API',
                    'troubleshooting' => [
                        'Check if your API credentials are valid',
                        'Verify internet connection',
                        'Ensure Monnify service is available'
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'General error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify Monnify payment - Sandbox Mode
     */
    public function monnifyVerifyPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_reference' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction reference is required',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $transactionReference = $request->input('transaction_reference');
            
            // Use real Monnify sandbox API for verification
            try {
                $response = Monnify::transactions()->status($transactionReference);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Monnify sandbox payment verification',
                    'sandbox_mode' => true,
                    'monnify_response' => $response
                ], 200);
                
            } catch (\Exception $monnifyError) {
                // If Monnify API fails, return detailed error info
                return response()->json([
                    'status' => 'error',
                    'message' => 'Monnify sandbox verification failed',
                    'error' => $monnifyError->getMessage(),
                    'sandbox_mode' => true,
                    'transaction_reference' => $transactionReference,
                    'note' => 'This error is from the actual Monnify sandbox API',
                    'troubleshooting' => [
                        'Verify the transaction reference is valid',
                        'Check if transaction exists in Monnify sandbox',
                        'Ensure API credentials are correct'
                    ]
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'General error occurred during verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Monnify webhook handler
     */
    /**
     * Test Monnify configuration (simple version)
     */
    public function testConnection(): JsonResponse
    {
        try {
            $config = [
                'api_key' => config('monnify.api_key'),
                'secret_key' => config('monnify.secret_key'), 
                'contract_code' => config('monnify.contract_code'),
                'environment' => config('monnify.environment'),
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Monnify configuration loaded successfully',
                'config' => [
                    'api_key' => substr($config['api_key'] ?? '', 0, 8) . '...',
                    'secret_key' => substr($config['secret_key'] ?? '', 0, 8) . '...',
                    'contract_code' => $config['contract_code'],
                    'environment' => $config['environment'],
                ],
                'package_status' => class_exists('Monnify\MonnifyLaravel\Facades\Monnify') ? 'installed' : 'not_found'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Monnify configuration error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Monnify configuration and API connection
     */  
    public function monnifyTestConnection(): JsonResponse
    {
        try {
            // Test configuration values
            $config = [
                'api_key' => config('monnify.api_key'),
                'api_secret' => config('monnify.secret_key'),
                'contract_code' => config('monnify.contract_code'),
                'environment' => config('monnify.environment'),
                'base_url' => config('monnify.base_url')
            ];
            
            // Check if config values exist
            $missingConfig = [];
            foreach ($config as $key => $value) {
                if (empty($value)) {
                    $missingConfig[] = $key;
                }
            }
            
            if (!empty($missingConfig)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing Monnify configuration',
                    'missing_config' => $missingConfig,
                    'note' => 'Check your .env file and config/monnify.php'
                ], 400);
            }
            
            // Test API connection by making a simple request
            try {
                // Try to get all transactions as a connection test
                $response = Monnify::transactions()->all(['page' => 1, 'size' => 1]);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Monnify sandbox API connection successful',
                    'config_status' => 'All configurations present',
                    'environment' => $config['environment'],
                    'base_url' => $config['base_url'],
                    'api_response' => 'Connected successfully',
                    'connection_test' => 'API is accessible',
                    'next_steps' => [
                        'You can now initialize payments',
                        'Use POST /api/v1/monnify/initialize to create payments',
                        'Use POST /api/v1/monnify/verify to check payment status'
                    ]
                ], 200);
                
            } catch (\Exception $apiError) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Monnify API connection failed',
                    'error' => $apiError->getMessage(),
                    'config_status' => 'Configurations present but API connection failed',
                    'troubleshooting' => [
                        'Verify your API credentials are correct',
                        'Check if your internet connection is working',
                        'Ensure Monnify sandbox is accessible',
                        'Try again in a few minutes'
                    ]
                ], 400);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configuration test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monnify webhook handler
     */
    public function monnifyWebhook(Request $request): JsonResponse
    {
        try {
            // Log the webhook data for debugging
            \Log::info('Monnify Webhook Received', $request->all());

            // Verify the webhook signature if needed
            $monnify = new Monnify();
            
            // Get the webhook data
            $webhookData = $request->all();
            
            // Process the webhook based on event type
            $eventType = $webhookData['eventType'] ?? 'SUCCESSFUL_TRANSACTION';
            
            switch ($eventType) {
                case 'SUCCESSFUL_TRANSACTION':
                    // Handle successful transaction
                    $transactionData = $webhookData['eventData'] ?? $webhookData;
                    
                    // You can add your business logic here
                    // For example: update order status, send confirmation email, etc.
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Webhook processed successfully',
                        'data' => [
                            'event_type' => $eventType,
                            'transaction_reference' => $transactionData['transactionReference'] ?? null,
                            'payment_reference' => $transactionData['paymentReference'] ?? null,
                            'amount_paid' => $transactionData['amountPaid'] ?? null,
                            'payment_status' => $transactionData['paymentStatus'] ?? 'PAID',
                            'processed_at' => now()->toISOString()
                        ]
                    ], 200);

                case 'FAILED_TRANSACTION':
                    // Handle failed transaction
                    return response()->json([
                        'status' => 'received',
                        'message' => 'Failed transaction webhook received',
                        'event_type' => $eventType
                    ], 200);

                default:
                    return response()->json([
                        'status' => 'received',
                        'message' => 'Webhook received',
                        'event_type' => $eventType
                    ], 200);
            }

        } catch (\Exception $e) {
            \Log::error('Monnify Webhook Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monnify callback handler
     */
    public function monnifyCallback(Request $request): JsonResponse
    {
        try {
            $paymentReference = $request->input('paymentReference');
            
            if (!$paymentReference) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment reference not found'
                ], 400);
            }

            // Verify the payment using Monnify facade
            $verificationResponse = Monnify::transactions()->getTransactionStatus($paymentReference);

            if ($verificationResponse['requestSuccessful']) {
                $paymentData = $verificationResponse['responseBody'];
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment callback processed successfully',
                    'data' => [
                        'payment_reference' => $paymentData['paymentReference'],
                        'payment_status' => $paymentData['paymentStatus'],
                        'amount_paid' => $paymentData['amountPaid'],
                        'customer_name' => $paymentData['customerName'],
                        'customer_email' => $paymentData['customerEmail']
                    ]
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed in callback',
                    'error' => $verificationResponse['responseMessage']
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Callback processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}