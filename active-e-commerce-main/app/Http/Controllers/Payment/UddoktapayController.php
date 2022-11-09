<?php

namespace App\Http\Controllers\Payment;

use App\Library\UddoktaPay;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Models\CombinedOrder;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CheckoutController;
use Session;
use Auth;

class UddoktapayController extends Controller
{
    public function pay()
    {
        if (Auth::user()->email == null) {
            $email = 'customer@exmaple.com';
        } else {
            $email = Auth::user()->email;
        }


        $amount = 0;
        if (Session::has('payment_type')) {
            if (Session::get('payment_type') == 'cart_payment') {
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = round($combined_order->grand_total);
            } elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = round(Session::get('payment_data')['amount']);
            } elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
            } elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
            }
        }

        $fields = array(
            'full_name' => Auth::user()->name,
            'email' => $email,
            'amount' => $amount,
            'metadata' => [
                'user_id'   => Auth::user()->id,
                'payment_type' => Session::get('payment_type'),
                'combined_order_id' => Session::get('combined_order_id'),
                'payment_data'      => Session::get('payment_data')
            ],
            'redirect_url' => route('uddoktapay.success'),
            'cancel_url' => route('uddoktapay.cancel'),
            'webhook_url' => route('uddoktapay.webhook')
        );


        $paymentUrl = UddoktaPay::init_payment($fields);
        return redirect($paymentUrl);
    }


    public function webhook(Request $request)
    {

        $headerApi = isset($_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY']) ? $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] : null;

        if ($headerApi == null) {
            return response("Api key not found", 403);
        }

        if ($headerApi != env("UDDOKTAPAY_API_KEY")) {
            return response("Unauthorized Action", 403);
        }

        $data = $request->getContent();

        if (isset($data['status']) && $data['status'] === 'COMPLETED') {
            if ('cart_payment' == $data['metadata']['payment_type']) {
                return (new CheckoutController)->checkout_done($data['metadata']['combined_order_id'], json_encode($request->all()));
            }

            if ('wallet_payment' == $data['metadata']['payment_type']) {
                return (new WalletController)->wallet_payment_done_uddoktapay($data['metadata']['payment_data'], $data['metadata']['user_id'], json_encode($request->all()));
            }

            if ('customer_package_payment' == $data['metadata']['payment_type']) {
                return (new CustomerPackageController)->purchase_payment_done_uddoktapay($data['metadata']['payment_data'], json_encode($request->all()));
            }
            if ('seller_package_payment' == $data['metadata']['payment_type']) {
                return (new SellerPackageController)->purchase_payment_done($data['metadata']['payment_data'], json_encode($request->all()));
            }
        }
    }

    public function success(Request $request)
    {

        $data = $request->all();
        if (isset($data['invoice_id'])) {
            $result = UddoktaPay::ipn($data['invoice_id']);
            if ($result['status'] === 'COMPLETED') {
                if ($result['metadata']['payment_type'] == 'cart_payment') {
                    return (new CheckoutController)->checkout_done(Session::get('combined_order_id'), json_encode($request->all()));
                }

                if ($result['metadata']['payment_type'] == 'wallet_payment') {
                    return (new WalletController)->wallet_payment_done(Session::get('payment_data'), json_encode($request->all()));
                }

                if ($result['metadata']['payment_type'] == 'customer_package_payment') {
                    return (new CustomerPackageController)->purchase_payment_done(Session::get('payment_data'), json_encode($request->all()));
                }
                if ($result['metadata']['payment_type'] == 'seller_package_payment') {
                    return (new SellerPackageController)->purchase_payment_done(Session::get('payment_data'), json_encode($request->all()));
                }
            } else {
                flash(translate('Payment pending'))->error();
                return redirect()->route('cart');
            }
        } else {
            flash(translate('Payment failed'))->error();
            return redirect()->route('cart');
        }
    }

    public function cancel(Request $request)
    {
        flash(translate('Payment failed'))->error();
        return redirect()->route('cart');
    }
}
