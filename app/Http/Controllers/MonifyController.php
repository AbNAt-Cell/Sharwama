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
        $paymentReference = $request->input('paymentReference');
        $transactionReference = $request->input('transactionReference');

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');
        }

        // Verify transaction
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->base_url . '/api/v2/transactions/' . urlencode($transactionReference));

            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['requestSuccessful'] && 
                    isset($responseData['responseBody']['paymentStatus']) && 
                    $responseData['responseBody']['paymentStatus'] === 'PAID') {

                    // Payment successful
                    $this->payment::where(['transaction_id' => $paymentReference])->update([
                        'payment_method' => 'monnify',
                        'is_paid' => 1,
                        'transaction_id' => $transactionReference,
                    ]);

                    $data = $this->payment::where(['transaction_id' => $paymentReference])->first();

                    if (isset($data) && function_exists($data->success_hook)) {
                        call_user_func($data->success_hook, $data);
                    }

                    return $this->payment_response($data, 'success');
                }
            }

            // Payment failed or pending
            $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');

        } catch (\Exception $e) {
            Log::error('Monnify Callback Exception', ['error' => $e->getMessage()]);
            $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');
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
