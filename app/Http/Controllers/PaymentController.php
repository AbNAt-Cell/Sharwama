<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\Model\CustomerAddress;
use App\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Traits\Payment;
use Illuminate\Support\Facades\Log;
use App\Models\Payment as PaymentModel;        // <— for DB storage
use Monnify;                                   // <— Monnify Facade
use function App\CentralLogics\translate;

class PaymentController extends Controller
{
    public function __construct(){
        // Removed problematic eval() code to fix class conflict
    }

    public function payment(Request $request)
    {
        if (!session()->has('payment_method')) {
            session()->put('payment_method', 'ssl_commerz');
        }

        $params = explode('&&', base64_decode($request['token']));
        foreach ($params as $param) {
            $data = explode('=', $param);
            if ($data[0] == 'customer_id') {
                session()->put('customer_id', $data[1]);
            } elseif ($data[0] == 'callback') {
                session()->put('callback', $data[1]);
            } elseif ($data[0] == 'order_amount') {
                session()->put('order_amount', $data[1]);
            } elseif ($data[0] == 'is_guest') {
                session()->put('is_guest', $data[1]);
            } elseif ($data[0] == 'product_ids') {
                session()->put('product_ids', $data[1]);
            }
        }

        $order_amount = session('order_amount');
        $customer_id  = session('customer_id');
        $is_guest     = session('is_guest') == 1 ? 1 : 0;

        if (!isset($order_amount)) {
            return response()->json(['errors' => ['message' => 'Amount not found']], 403);
        }

        if ($order_amount < 0) {
            return response()->json(['errors' => ['message' => 'Amount is less than 0']], 403);
        }

        if (!$request->has('payment_method')) {
            return response()->json(['errors' => ['message' => 'Payment not found']], 403);
        }

        //partial payment validation
        if ($request['is_partial'] == 1){
            if ($is_guest == 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('partial order does not applicable for guest user')]]], 403);
            }

            $customer = User::firstWhere(['id' => $customer_id, 'is_active' => 1]);

            if (Helpers::get_business_settings('wallet_status') != 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('customer_wallet_status_is_disable')]]], 403);
            }
            if (isset($customer) && $customer->wallet_balance < 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('since your wallet balance is less than 1, you can not place partial order')]]], 403);
            }
            $order_amount -= $customer->wallet_balance;
        }

        $additional_data = [
            'business_name' => Helpers::get_business_settings('restaurant_name') ?? '',
            'business_logo' => asset('storage/app/public/restaurant/' . Helpers::get_business_settings('logo'))
        ];

        //add fund to wallet
        $is_add_fund = $request['is_add_fund'];
        if ($is_add_fund == 1) {
            $add_fund_to_wallet = Helpers::get_business_settings('add_fund_to_wallet');
            if ($add_fund_to_wallet == 0){
                return response()->json(['errors' => ['message' => 'Add fund to wallet is not active']], 403);
            }

            $customer = User::firstWhere(['id' => $customer_id, 'is_active' => 1]);
            if (!isset($customer)) {
                return response()->json(['errors' => ['message' => 'Customer not found']], 403);
            }

            $payer = new Payer($customer['f_name'].' '.$customer['l_name'], $customer['email'], $customer['phone'], '');

            $payment_info = new PaymentInfo(
                success_hook: 'add_fund_success',
                failure_hook: 'add_fund_fail',
                currency_code: Helpers::currency_code(),
                payment_method: $request->payment_method,
                payment_platform: $request->payment_platform,
                payer_id: $customer->id,
                receiver_id: null,
                additional_data: $additional_data,
                payment_amount: $order_amount,
                external_redirect_link: session('callback'),
                attribute: 'add-fund',
                attribute_id: time()
            );

            $receiver_info = new Receiver('receiver_name','example.png');
            $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);
            return redirect($redirect_link);
        }

        //order place
        if ($is_guest == 1) {//guest order
            $address = CustomerAddress::where(['user_id' => $customer_id, 'is_guest' => 1])->first();
            if ($address){
                $customer = collect([
                    'f_name' => $address['contact_person_name'] ?? '',
                    'l_name' => '',
                    'phone' => $address['contact_person_number'] ?? '',
                    'email' => '',
                ]);
            }else{
                $customer = collect([
                    'f_name' => 'example',
                    'l_name' => 'customer',
                    'phone' => '+88011223344',
                    'email' => 'example@customer.com',
                ]);
            }
        } else { //normal order
            $customer = User::firstWhere(['id' => $customer_id, 'is_active' => 1]);
            if (!isset($customer)) {
                return response()->json(['errors' => ['message' => 'Customer not found']], 403);
            }
            $customer = collect([
                'f_name' => $customer['f_name'],
                'l_name' => $customer['l_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
            ]);
        }

        $payer = new Payer($customer['f_name'] . ' ' . $customer['l_name'] , $customer['email'], $customer['phone'], '');

        $payment_info = new PaymentInfo(
            success_hook: 'order_place',
            failure_hook: 'order_cancel',
            currency_code: Helpers::currency_code(),
            payment_method: $request->payment_method,
            payment_platform: $request->payment_platform,
            payer_id: session('customer_id'),
            receiver_id: '100',
            additional_data: $additional_data,
            payment_amount: $order_amount,
            external_redirect_link: session('callback'),
            attribute: 'order',
            attribute_id: time()
        );

        $receiver_info = new Receiver('receiver_name','example.png');

        $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);

        return redirect($redirect_link);
    }

    public function success(Request $request)
    {
        if (session()->has('callback')) {
            return redirect(session('callback') . '/success');
        }
        return response()->json(['message' => 'Payment succeeded'], 200);
    }

    public function fail(): JsonResponse|Redirector|RedirectResponse|Application
    {
        if (session()->has('callback')) {
            return redirect(session('callback') . '/fail');
        }
        return response()->json(['message' => 'Payment failed'], 403);
    }

    /* -------------------------------------------------------------
     |  MONNIFY INTEGRATION
     |--------------------------------------------------------------
     */
    public function monnifyInitialize(Request $request)
    {
        $request->validate([
            'amount'   => 'required|numeric|min:1',
            'email'    => 'required|email',
            'name'     => 'required|string',
            'currency' => 'nullable|string'
        ]);

        $amount   = $request->amount;
        $currency = $request->currency ?? 'NGN';
        $email    = $request->email;
        $name     = $request->name;

        $reference = 'PAY-' . uniqid();

        $payment = PaymentModel::create([
            'reference'      => $reference,
            'amount'         => $amount,
            'currency'       => $currency,
            'customer_name'  => $name,
            'customer_email' => $email,
            'status'         => 'pending',
        ]);

        $response = Monnify::initializePayment([
            'amount'           => $amount,
            'customerName'     => $name,
            'customerEmail'    => $email,
            'paymentReference' => $reference,
            'paymentDescription' => 'Order Payment',
            'currencyCode'     => $currency,
            'redirectUrl'      => route('monnify.webhook'),
        ]);

        return response()->json([
            'payment_url' => $response['checkoutUrl'] ?? null,
            'reference'   => $reference,
        ]);
    }

    public function monnifyWebhook(Request $request)
    {
        Log::info('Monnify webhook:', $request->all());

        $reference   = $request->input('paymentReference');
        $transaction = $request->input('transactionReference');
        $status      = strtolower($request->input('paymentStatus'));

        $payment = PaymentModel::where('reference', $reference)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->update([
            'status'          => $status,
            'transaction_id'  => $transaction,
            'gateway_response'=> json_encode($request->all()),
        ]);

        return response()->json(['message' => 'ok']);
    }
}
