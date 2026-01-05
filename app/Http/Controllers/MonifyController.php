<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MonifyController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private $user;
    private $config_values;
    private $base_url;
    private $api_key;
    private $secret_key;
    private $contract_code;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('monnify', 'payment_config');
        $values = false;

        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if ($values) {
            $this->config_values = $values;
            $this->api_key = $values->api_key ?? config('monnify.api_key');
            $this->secret_key = $values->secret_key ?? config('monnify.secret_key');
            $this->contract_code = $values->contract_code ?? config('monnify.contract_code');

            // Set base URL based on mode
            $environment = ($config->mode == 'live') ? 'LIVE' : 'SANDBOX';
            $this->base_url = config('monnify.base_url')[$environment] ?? 'https://sandbox.monnify.com';
        } else {
            // Fallback to config file
            $environment = config('monnify.environment', 'SANDBOX');
            $this->api_key = config('monnify.api_key');
            $this->secret_key = config('monnify.secret_key');
            $this->contract_code = config('monnify.contract_code');
            $this->base_url = config('monnify.base_url')[$environment] ?? 'https://sandbox.monnify.com';
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    /**
     * Get access token from Monnify
     */
    private function getAccessToken()
    {
        try {
            $response = Http::withBasicAuth($this->api_key, $this->secret_key)
                ->post($this->base_url . '/api/v1/auth/login');

            if ($response->successful()) {
                $data = $response->json();
                return $data['responseBody']['accessToken'] ?? null;
            }

            Log::error('Monnify Auth Failed', ['response' => $response->json()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Monnify Auth Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * API endpoint to verify payment status immediately
     * Called by Flutter app after payment callback for instant verification
     */
    public function verifyPaymentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment reference',
                'errors' => $validator->errors()
            ], 400);
        }

        $paymentReference = $request->input('payment_reference');

        try {
            // Get access token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate with payment gateway'
                ], 500);
            }

            // Verify transaction with Monnify API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->base_url . '/api/v2/transactions/' . urlencode($paymentReference));

            if (!$response->successful()) {
                Log::warning('Monnify Verification Failed', [
                    'paymentReference' => $paymentReference,
                    'status' => $response->status()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment',
                    'payment_status' => 'PENDING'
                ], 200);
            }

            $data = $response->json();

            if ($data['requestSuccessful'] && isset($data['responseBody'])) {
                $paymentData = $data['responseBody'];
                $paymentStatus = $paymentData['paymentStatus'];

                // Find payment record
                $payment_record = $this->payment::where('transaction_id', $paymentReference)->first();

                if ($payment_record && $paymentStatus === 'PAID' && !$payment_record->is_paid) {
                    // Update payment record
                    $payment_record->update([
                        'payment_method' => 'monnify',
                        'is_paid' => 1,
                        'transaction_id' => $paymentData['transactionReference'],
                    ]);

                    // Call success hook to fulfill order
                    if (function_exists($payment_record->success_hook)) {
                        call_user_func($payment_record->success_hook, $payment_record);
                    }

                    Log::info('Monnify Payment Verified and Processed', [
                        'paymentReference' => $paymentReference,
                        'transactionReference' => $paymentData['transactionReference']
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'payment_status' => $paymentStatus,
                    'transaction_reference' => $paymentData['transactionReference'] ?? null,
                    'amount_paid' => $paymentData['amountPaid'] ?? null,
                    'is_paid' => $payment_record ? $payment_record->is_paid : 0
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid response from payment gateway',
                'payment_status' => 'UNKNOWN'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Monnify Verification Exception', [
                'error' => $e->getMessage(),
                'paymentReference' => $paymentReference
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initialize Monnify payment page
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);
        $reference = 'PAY-' . time() . '-' . Str::random(10);

        return view('payment-gateway.monnify', compact('data', 'payer', 'reference'));
    }

    /**
     * Initialize payment transaction
     */
    public function initializePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);
        $reference = 'PAY-' . $data->id . '-' . time();

        // Set external_redirect_link from session callback if not already set
        if (empty($data->external_redirect_link) && session()->has('callback')) {
            $data->external_redirect_link = session('callback');
            $data->save();
        }

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return response()->json(['error' => 'Unable to authenticate with Monnify'], 500);
        }

        // Initialize transaction
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->base_url . '/api/v1/merchant/transactions/init-transaction', [
                        'amount' => round($data->payment_amount, 2),
                        'customerName' => $payer->name ?? 'Customer',
                        'customerEmail' => $payer->email ?? 'customer@example.com',
                        'paymentReference' => $reference,
                        'paymentDescription' => 'Order Payment - ' . $data->attribute_id,
                        'currencyCode' => $data->currency_code ?? 'NGN',
                        'contractCode' => $this->contract_code,
                        'redirectUrl' => route('monnify.callback', ['payment_id' => $data->id]),
                        'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER']
                    ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['requestSuccessful']) {
                    // Update payment with reference
                    $data->transaction_id = $reference;
                    $data->save();

                    $checkoutUrl = $responseData['responseBody']['checkoutUrl'];
                    return redirect()->away($checkoutUrl);
                }
            }

            Log::error('Monnify Init Failed', ['response' => $response->json()]);
            return response()->json(['error' => 'Payment initialization failed'], 500);

        } catch (\Exception $e) {
            Log::error('Monnify Init Exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Payment initialization error'], 500);
        }
    }

    /**
     * Handle Monnify callback after payment
     * NOTE: This is primarily for UX - webhook handles actual fulfillment
     */
    public function callback(Request $request)
    {
        Log::info('Monnify Callback Received', $request->all());

        // Monnify sends parameters with double ? instead of &, causing malformed URLs
        // Example: callback?payment_id=xxx?paymentReference=yyy&transactionReference=zzz
        $paymentReference = $request->input('paymentReference');
        $paymentId = $request->query('payment_id');

        // Fallback parsing for malformed URL
        if (!$paymentReference && $paymentId && strpos($paymentId, '?paymentReference=') !== false) {
            parse_str(substr($paymentId, strpos($paymentId, '?') + 1), $params);
            $paymentReference = $params['paymentReference'] ?? null;
        }

        // Look up payment by ID or transaction reference
        $payment_data = $this->payment::where('id', $paymentId)
            ->orWhere('transaction_id', $paymentReference)
            ->first();

        if (!$payment_data) {
            Log::error('Monnify Callback: Payment not found', [
                'paymentId' => $paymentId,
                'paymentReference' => $paymentReference
            ]);
            return view('payment-callback', [
                'status' => 'fail',
                'reference' => $paymentReference ?? 'N/A',
                'redirect_url' => null
            ]);
        }

        // Optional: Quick API verify for instant UX feedback
        // But show "Processing..." if pending - webhook will handle final fulfillment
        try {
            $accessToken = $this->getAccessToken();
            $status = 'processing'; // Default status

            if ($accessToken && $paymentReference) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken
                ])->get($this->base_url . '/api/v2/transactions/' . urlencode($paymentReference));

                if ($response->successful()) {
                    $responseData = $response->json();
                    Log::info('Monnify Callback Verification', $responseData);

                    if ($responseData['requestSuccessful'] && isset($responseData['responseBody']['paymentStatus'])) {
                        $apiStatus = $responseData['responseBody']['paymentStatus'];

                        // Show success only if definitely PAID, otherwise show processing
                        if ($apiStatus === 'PAID') {
                            $status = 'success';
                        } elseif ($apiStatus === 'FAILED' || $apiStatus === 'CANCELLED') {
                            $status = 'fail';
                        }
                        // For PENDING or other statuses, keep 'processing'
                    }
                }
            }

            // Prepare redirect URL based on status
            $redirectUrl = null;
            if ($payment_data->external_redirect_link) {
                if ($status === 'success') {
                    $redirectUrl = $payment_data->external_redirect_link . '/success';
                } elseif ($status === 'fail') {
                    $redirectUrl = $payment_data->external_redirect_link . '/fail';
                } else {
                    // Processing - redirect to a processing/pending page
                    $redirectUrl = $payment_data->external_redirect_link . '/processing';
                }
            }

            return view('payment-callback', [
                'status' => $status,
                'reference' => $paymentReference ?? $paymentId,
                'redirect_url' => $redirectUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Monnify Callback Exception', [
                'error' => $e->getMessage(),
                'paymentReference' => $paymentReference
            ]);

            // Show processing status on error - webhook will handle fulfillment
            $redirectUrl = $payment_data->external_redirect_link
                ? $payment_data->external_redirect_link . '/processing'
                : null;

            return view('payment-callback', [
                'status' => 'processing',
                'reference' => $paymentReference ?? 'N/A',
                'redirect_url' => $redirectUrl
            ]);
        }
    }


    /**
     * Handle Monnify webhook notifications
     * Validates signature, prevents duplicates, and handles all transaction statuses
     */
    public function webhook(Request $request)
    {
        Log::info('Monnify Webhook Received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        // Validate webhook signature for security
        $signature = $request->header('monnify-signature');
        if (!$this->validateWebhookSignature($request->getContent(), $signature)) {
            Log::warning('Monnify Webhook: Invalid signature');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $eventType = $request->input('eventType');
        $eventData = $request->input('eventData');

        if (!$eventData) {
            Log::error('Monnify Webhook: Missing event data');
            return response()->json(['status' => 'missing data'], 400);
        }

        $paymentReference = $eventData['paymentReference'] ?? null;
        $transactionReference = $eventData['transactionReference'] ?? null;
        $paymentStatus = $eventData['paymentStatus'] ?? null;
        $amountPaid = $eventData['amountPaid'] ?? null;

        if (!$paymentReference) {
            Log::error('Monnify Webhook: Missing payment reference');
            return response()->json(['status' => 'missing reference'], 400);
        }

        // Find payment record
        // First try by transaction_id
        $payment_data = $this->payment::where('transaction_id', $paymentReference)->first();

        // If not found, extract payment UUID from paymentReference and search by id
        // PaymentReference format: PAY-{uuid}-{timestamp}
        if (!$payment_data && preg_match('/PAY-([a-f0-9\-]{36})-\d+/', $paymentReference, $matches)) {
            $paymentId = $matches[1];
            $payment_data = $this->payment::where('id', $paymentId)->first();

            if ($payment_data) {
                Log::info('Monnify Webhook: Found payment by ID extraction', [
                    'paymentReference' => $paymentReference,
                    'extractedId' => $paymentId
                ]);
            }
        }

        if (!$payment_data) {
            Log::error('Monnify Webhook: Payment not found', ['paymentReference' => $paymentReference]);
            return response()->json(['status' => 'payment not found'], 404);
        }

        // Prevent duplicate processing
        if ($payment_data->is_paid == 1) {
            Log::info('Monnify Webhook: Payment already processed', ['paymentReference' => $paymentReference]);
            return response()->json(['status' => 'already processed'], 200);
        }

        // Handle different payment statuses
        if ($eventType === 'SUCCESSFUL_TRANSACTION' && $paymentStatus === 'PAID') {
            // Optional: Re-verify via API for extra security
            $accessToken = $this->getAccessToken();
            if ($accessToken) {
                try {
                    $response = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
                        ->get($this->base_url . '/api/v2/transactions/' . urlencode($paymentReference));

                    if ($response->successful()) {
                        $apiData = $response->json();
                        if ($apiData['requestSuccessful'] && isset($apiData['responseBody']['paymentStatus'])) {
                            $apiStatus = $apiData['responseBody']['paymentStatus'];

                            if ($apiStatus !== 'PAID') {
                                Log::warning('Monnify Webhook: Status mismatch after API verify', [
                                    'paymentReference' => $paymentReference,
                                    'webhookStatus' => $paymentStatus,
                                    'apiStatus' => $apiStatus
                                ]);
                                return response()->json(['status' => 'mismatch'], 200);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Monnify Webhook: API verification failed', [
                        'error' => $e->getMessage(),
                        'paymentReference' => $paymentReference
                    ]);
                    // Continue with webhook data if API verification fails
                }
            }

            // Update payment record
            $payment_data->update([
                'payment_method' => 'monnify',
                'is_paid' => 1,
                'transaction_id' => $transactionReference,
            ]);

            Log::info('Monnify Webhook: Payment marked as paid', [
                'paymentReference' => $paymentReference,
                'transactionReference' => $transactionReference,
                'amount' => $amountPaid
            ]);

            // Call success hook to fulfill order
            if (isset($payment_data) && function_exists($payment_data->success_hook)) {
                call_user_func($payment_data->success_hook, $payment_data);
            }

            return response()->json(['status' => 'success'], 200);
        } elseif ($paymentStatus === 'FAILED' || $paymentStatus === 'CANCELLED') {
            Log::warning('Monnify Webhook: Payment failed or cancelled', [
                'paymentReference' => $paymentReference,
                'status' => $paymentStatus
            ]);

            // Call failure hook
            if (function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }

            return response()->json(['status' => 'payment failed'], 200);
        } else {
            Log::info('Monnify Webhook: Payment pending or unknown status', [
                'paymentReference' => $paymentReference,
                'status' => $paymentStatus
            ]);
            return response()->json(['status' => 'pending'], 200);
        }
    }

    /**
     * Validate Monnify webhook signature
     */
    private function validateWebhookSignature($payload, $signature)
    {
        // Get Monnify API secret from config
        $config = $this->payment_config('monnify', 'payment_config');

        if (!$config) {
            Log::warning('Monnify: Config not found, skipping signature validation');
            return true; // Allow webhook if config is missing (for backward compatibility)
        }

        $values = null;
        if ($config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif ($config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if (!$values || !isset($values->secret_key)) {
            Log::warning('Monnify: Secret key not found, skipping signature validation');
            return true; // Allow webhook if secret key is missing
        }

        // Compute expected signature
        $computedSignature = hash_hmac('sha512', $payload, $values->secret_key);

        // Compare signatures
        return hash_equals($computedSignature, $signature);
    }
}
