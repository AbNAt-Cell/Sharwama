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
     */
    public function callback(Request $request)
    {
        // Log all incoming parameters for debugging
        Log::info('Monnify Callback Received', $request->all());

        $paymentReference = $request->input('paymentReference');
        $transactionReference = $request->input('transactionReference');
        $paymentStatus = $request->input('paymentStatus');

        // Look up payment by transaction_id (our paymentReference)
        $payment_data = $this->payment::where('transaction_id', $paymentReference)->first();

        if (!$payment_data) {
            Log::error('Monnify Callback: Payment not found', ['paymentReference' => $paymentReference]);
            return view('payment-callback', [
                'status' => 'fail',
                'reference' => $paymentReference ?? 'N/A',
                'redirect_url' => null
            ]);
        }

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('Monnify Callback: Failed to get access token');

            if (function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }

            $redirectUrl = $payment_data->external_redirect_link
                ? $payment_data->external_redirect_link . '/fail'
                : null;

            return view('payment-callback', [
                'status' => 'fail',
                'reference' => $paymentReference ?? 'N/A',
                'redirect_url' => $redirectUrl
            ]);
        }

        // Verify transaction with Monnify API
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->base_url . '/api/v2/transactions/' . urlencode($transactionReference));

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Monnify Transaction Verification', $responseData);

                if (
                    $responseData['requestSuccessful'] &&
                    isset($responseData['responseBody']['paymentStatus']) &&
                    $responseData['responseBody']['paymentStatus'] === 'PAID'
                ) {

                    // Payment successful - update payment record
                    $payment_data->update([
                        'payment_method' => 'monnify',
                        'is_paid' => 1,
                        'transaction_id' => $transactionReference,
                    ]);

                    Log::info('Monnify Payment Successful', [
                        'payment_id' => $payment_data->id,
                        'transaction_id' => $transactionReference
                    ]);

                    // Call success hook
                    if (function_exists($payment_data->success_hook)) {
                        call_user_func($payment_data->success_hook, $payment_data);
                    }

                    // Prepare redirect URL
                    $redirectUrl = $payment_data->external_redirect_link
                        ? $payment_data->external_redirect_link . '/success'
                        : null;

                    return view('payment-callback', [
                        'status' => 'success',
                        'reference' => $transactionReference,
                        'redirect_url' => $redirectUrl
                    ]);
                }
            }

            // Payment failed or pending
            Log::warning('Monnify Payment Failed or Pending', [
                'paymentReference' => $paymentReference,
                'paymentStatus' => $paymentStatus
            ]);

            if (function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }

            $redirectUrl = $payment_data->external_redirect_link
                ? $payment_data->external_redirect_link . '/fail'
                : null;

            return view('payment-callback', [
                'status' => 'fail',
                'reference' => $paymentReference,
                'redirect_url' => $redirectUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Monnify Callback Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }

            $redirectUrl = $payment_data->external_redirect_link
                ? $payment_data->external_redirect_link . '/fail'
                : null;

            return view('payment-callback', [
                'status' => 'fail',
                'reference' => $paymentReference ?? 'N/A',
                'redirect_url' => $redirectUrl
            ]);
        }
    }


    /**
     * Handle Monnify webhook notifications
     */
    public function webhook(Request $request)
    {
        Log::info('Monnify Webhook Received', $request->all());

        $eventType = $request->input('eventType');
        $eventData = $request->input('eventData');

        if ($eventType === 'SUCCESSFUL_TRANSACTION') {
            $paymentReference = $eventData['paymentReference'] ?? null;
            $transactionReference = $eventData['transactionReference'] ?? null;
            $paymentStatus = $eventData['paymentStatus'] ?? null;

            if ($paymentStatus === 'PAID' && $paymentReference) {
                $this->payment::where(['transaction_id' => $paymentReference])->update([
                    'payment_method' => 'monnify',
                    'is_paid' => 1,
                    'transaction_id' => $transactionReference,
                ]);

                $data = $this->payment::where(['transaction_id' => $paymentReference])->first();

                if (isset($data) && function_exists($data->success_hook)) {
                    call_user_func($data->success_hook, $data);
                }
            }
        }

        return response()->json(['status' => 'received'], 200);
    }
}
