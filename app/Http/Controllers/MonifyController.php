<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monnify\MonnifyLaravel\Facades\Monnify;

class MonifyController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $this->payment = $payment;
        $this->user = $user;
    }

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
            // Use Monnify package to get transaction status
            $response = Monnify::transactions()->getStatus($paymentReference);

            if ($response['requestSuccessful'] && isset($response['responseBody'])) {
                $paymentData = $response['responseBody'];
                $paymentStatus = $paymentData['paymentStatus'];

                $payment_record = $this->payment::where('transaction_id', $paymentReference)->first();

                if ($payment_record && $paymentStatus === 'PAID' && !$payment_record->is_paid) {
                    $payment_record->update([
                        'payment_method' => 'monnify',
                        'is_paid' => 1,
                        'transaction_id' => $paymentData['transactionReference'],
                    ]);

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

        if (empty($data->external_redirect_link) && session()->has('callback')) {
            $data->external_redirect_link = session('callback');
            $data->save();
        }

        try {
            // Use Monnify package to initialize transaction
            $paymentData = [
                'amount' => round($data->payment_amount, 2),
                'customerName' => $payer->name ?? 'Customer',
                'customerEmail' => $payer->email ?? 'customer@example.com',
                'paymentReference' => $reference,
                'paymentDescription' => 'Order Payment - ' . $data->attribute_id,
                'currencyCode' => $data->currency_code ?? 'NGN',
                'contractCode' => config('monnify.contract_code'),
                'redirectUrl' => route('monnify.callback', ['payment_id' => $data->id]),
                'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER']
            ];

            $response = Monnify::transactions()->initialise($paymentData);

            if ($response['requestSuccessful']) {
                $data->transaction_id = $reference;
                $data->save();

                $checkoutUrl = $response['responseBody']['checkoutUrl'];
                return redirect()->away($checkoutUrl);
            }

            Log::error('Monnify Init Failed', ['response' => $response]);
            return response()->json(['error' => 'Payment initialization failed'], 500);

        } catch (\Exception $e) {
            Log::error('Monnify Init Exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Payment initialization error: ' . $e->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        Log::info('Monnify Callback Received', $request->all());

        $paymentReference = $request->input('paymentReference');
        $paymentId = $request->query('payment_id');

        // Clean payment_id if it contains query string
        if ($paymentId && strpos($paymentId, '?') !== false) {
            $paymentId = substr($paymentId, 0, strpos($paymentId, '?'));
        }

        // Extract payment reference from malformed payment_id if needed
        if (!$paymentReference && $request->query('payment_id') && strpos($request->query('payment_id'), '?paymentReference=') !== false) {
            parse_str(substr($request->query('payment_id'), strpos($request->query('payment_id'), '?') + 1), $params);
            $paymentReference = $params['paymentReference'] ?? null;
        }

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

        try {
            $status = 'processing';

            if ($paymentReference) {
                // Use Monnify package to verify payment
                $response = Monnify::transactions()->getStatus($paymentReference);
                Log::info('Monnify Callback Verification', $response);

                if ($response['requestSuccessful'] && isset($response['responseBody']['paymentStatus'])) {
                    $apiStatus = $response['responseBody']['paymentStatus'];
                    if ($apiStatus === 'PAID') {
                        $status = 'success';
                    } elseif (in_array($apiStatus, ['FAILED', 'CANCELLED'])) {
                        $status = 'fail';
                    }
                }
            }

            $redirectUrl = null;
            if ($payment_data->external_redirect_link) {
                $redirectUrl = $payment_data->external_redirect_link . '/' . $status;
            }

            return view('payment-callback', [
                'status' => $status,
                'reference' => $paymentReference ?? $paymentId,
                'redirect_url' => $redirectUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Monnify Callback Exception', ['error' => $e->getMessage()]);

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

    public function webhook(Request $request)
    {
        // Monnify webhook IP (recommended whitelisting)
        if ($request->ip() !== '35.242.133.146') {
            Log::warning('Monnify Webhook: Unauthorized IP', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized IP'], 403);
        }

        $rawPayload = $request->getContent();
        $signature = $request->header('monnify-signature');

        Log::info('Monnify Webhook Received', [
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'raw_payload' => $rawPayload
        ]);

        if (!$this->validateWebhookSignature($rawPayload, $signature)) {
            Log::warning('Monnify Webhook: Invalid signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Monnify Webhook: Invalid JSON payload', ['error' => json_last_error_msg()]);
            return response()->json(['message' => 'Invalid JSON'], 400);
        }

        $eventType = $payload['eventType'] ?? null;
        $eventData = $payload['eventData'] ?? [];

        $paymentReference = $eventData['paymentReference'] ?? null;
        $transactionReference = $eventData['transactionReference'] ?? null;
        $paymentStatus = $eventData['paymentStatus'] ?? null;
        $amountPaid = $eventData['amountPaid'] ?? null;

        if (!$paymentReference) {
            Log::error('Monnify Webhook: Missing payment reference');
            return response()->json(['message' => 'Missing payment reference'], 400);
        }

        $payment_data = $this->payment::where('transaction_id', $paymentReference)->first();

        if (!$payment_data && preg_match('/PAY-([a-f0-9\-]{36})-\d+/', $paymentReference, $matches)) {
            $paymentId = $matches[1];
            $payment_data = $this->payment::where('id', $paymentId)->first();
        }

        if (!$payment_data) {
            Log::error('Monnify Webhook: Payment not found', ['paymentReference' => $paymentReference]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($payment_data->is_paid) {
            Log::info('Monnify Webhook: Already processed', ['paymentReference' => $paymentReference]);
            return response()->json(['message' => 'Already processed'], 200);
        }

        if ($eventType === 'SUCCESSFUL_TRANSACTION' && $paymentStatus === 'PAID') {
            $payment_data->update([
                'payment_method' => 'monnify',
                'is_paid' => 1,
                'transaction_id' => $transactionReference,
            ]);

            if (function_exists($payment_data->success_hook)) {
                call_user_func($payment_data->success_hook, $payment_data);
            }

            Log::info('Monnify Webhook: Payment successful', [
                'paymentReference' => $paymentReference,
                'amount' => $amountPaid
            ]);

            return response()->json(['message' => 'Success'], 200);
        }

        if (in_array($paymentStatus, ['FAILED', 'CANCELLED'])) {
            if (function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            Log::warning('Monnify Webhook: Payment failed', ['status' => $paymentStatus]);
        } else {
            Log::info('Monnify Webhook: Pending/Other status', ['status' => $paymentStatus]);
        }

        return response()->json(['message' => 'Processed'], 200);
    }

    private function validateWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        if (empty($signature) || empty($rawPayload)) {
            Log::warning('Monnify Webhook: Missing signature or payload');
            return false;
        }

        $config = $this->payment_config('monnify', 'payment_config');
        if (!$config) {
            Log::error('Monnify Webhook: Payment config not found');
            return false;
        }

        $values = ($config->mode === 'live')
            ? json_decode($config->live_values)
            : json_decode($config->test_values);

        $clientSecret = $values->secret_key ?? null;
        if (!$clientSecret) {
            Log::error('Monnify Webhook: Client Secret not configured');
            return false;
        }

        $computed = hash_hmac('sha512', $rawPayload, $clientSecret);

        if (!hash_equals($computed, $signature)) {
            Log::warning('Monnify Webhook: Signature mismatch');
            return false;
        }

        return true;
    }
}