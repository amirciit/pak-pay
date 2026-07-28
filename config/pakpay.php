<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    |
    | The gateway used when you call PakPay methods without specifying one,
    | e.g. PakPay::checkout($request). Override per-call with
    | PakPay::gateway('easypaisa').
    |
    */

    'default' => env('PAKPAY_GATEWAY', 'jazzcash'),

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | A single switch that flips EVERY gateway between its sandbox and
    | production endpoints. Accepted values: "sandbox", "production".
    |
    */

    'mode' => env('PAKPAY_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Hosted-redirect Route
    |--------------------------------------------------------------------------
    |
    | The package registers a single GET route that renders the self-submitting
    | POST form used by hosted-redirect gateways. Customise its URL prefix and
    | the middleware applied to it here.
    |
    */

    'route' => [
        'prefix' => env('PAKPAY_ROUTE_PREFIX', 'pakpay'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hosted-redirect Payload Lifetime
    |--------------------------------------------------------------------------
    |
    | How many minutes a signed hosted-checkout payload stays in the cache
    | before its one-time token expires. Keep it short: it only has to survive
    | the browser hop between your checkout controller and the render route.
    | The token is consumed on first use regardless.
    |
    */

    'redirect_ttl' => (int) env('PAKPAY_REDIRECT_TTL', 10),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    |
    | Per-gateway credentials and endpoints. Never hardcode secrets here —
    | always read from env. Endpoint URLs marked "// VERIFY" must be checked
    | against the gateway's official integration documentation before use.
    |
    */

    'gateways' => [

        'jazzcash' => [
            'merchant_id' => env('JAZZCASH_MERCHANT_ID'),
            'password' => env('JAZZCASH_PASSWORD'),
            'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
            'return_url' => env('JAZZCASH_RETURN_URL'),

            // Currency + language defaults required by the JazzCash payload.
            'currency' => env('JAZZCASH_CURRENCY', 'PKR'),
            'language' => env('JAZZCASH_LANGUAGE', 'EN'),

            // SECURITY: when true, pp_Password is included in the *hosted* form,
            // which exposes it in the browser page source. JazzCash historically
            // requires it there; set this false if your Hosted Checkout does not,
            // so the merchant password is never sent to the client. The direct
            // wallet charge() always sends it server-to-server (safe).
            'hosted_send_password' => env('JAZZCASH_HOSTED_SEND_PASSWORD', true),

            'endpoints' => [
                // VERIFY against official JazzCash docs (Sandbox / Live portals).
                'sandbox' => [
                    'checkout' => 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/',
                    'wallet' => 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/Purchase/DoMWalletTransaction',
                    'status' => 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire',
                    'refund' => 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/Refund/DoRefund',
                ],
                'production' => [
                    'checkout' => 'https://payments.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/',
                    'wallet' => 'https://payments.jazzcash.com.pk/ApplicationAPI/API/Purchase/DoMWalletTransaction',
                    'status' => 'https://payments.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire',
                    'refund' => 'https://payments.jazzcash.com.pk/ApplicationAPI/API/Refund/DoRefund',
                ],
            ],
        ],

        'easypaisa' => [
            'store_id' => env('EASYPAISA_STORE_ID'),
            'hash_key' => env('EASYPAISA_HASH_KEY'),
            'account_num' => env('EASYPAISA_ACCOUNT_NUM'),
            'return_url' => env('EASYPAISA_RETURN_URL'),

            'endpoints' => [
                // VERIFY against official EasyPaisa (Telenor Microfinance) docs.
                'sandbox' => [
                    'checkout' => 'https://easypaystg.easypaisa.com.pk/easypay/Index.jsf',
                    'confirm' => 'https://easypaystg.easypaisa.com.pk/easypay/Confirm.jsf',
                    'mobile_account' => 'https://easypaystg.easypaisa.com.pk/easypay-service/rest/v4/initiate-transaction',
                    'status' => 'https://easypaystg.easypaisa.com.pk/easypay-service/rest/v4/inquire-transaction',
                ],
                'production' => [
                    'checkout' => 'https://easypay.easypaisa.com.pk/easypay/Index.jsf',
                    'confirm' => 'https://easypay.easypaisa.com.pk/easypay/Confirm.jsf',
                    'mobile_account' => 'https://easypay.easypaisa.com.pk/easypay-service/rest/v4/initiate-transaction',
                    'status' => 'https://easypay.easypaisa.com.pk/easypay-service/rest/v4/inquire-transaction',
                ],
            ],
        ],

        'safepay' => [
            // Public client key (safe to expose to the browser / Smart Button).
            'client_key' => env('SAFEPAY_CLIENT_KEY'),
            // Secret API key (server-side only; used for passport token + webhooks).
            'secret_key' => env('SAFEPAY_SECRET_KEY'),
            // Webhook signing secret from the Safepay dashboard.
            'webhook_secret' => env('SAFEPAY_WEBHOOK_SECRET'),
            'return_url' => env('SAFEPAY_RETURN_URL'),
            'cancel_url' => env('SAFEPAY_CANCEL_URL'),
            'currency' => env('SAFEPAY_CURRENCY', 'PKR'),

            'endpoints' => [
                // Hosts per the Safepay docs; sub-paths marked // VERIFY.
                'sandbox' => [
                    'api' => 'https://sandbox.api.getsafepay.com',
                    'checkout' => 'https://sandbox.api.getsafepay.com/checkout/pay', // VERIFY
                    'environment' => 'sandbox',
                ],
                'production' => [
                    'api' => 'https://api.getsafepay.com',
                    'checkout' => 'https://getsafepay.com/checkout/pay', // VERIFY
                    'environment' => 'production',
                ],
            ],

            'paths' => [
                'passport' => '/client/passport/v1/token',
                'order' => '/order/v1/init', // VERIFY against official Order API docs
                'status' => '/order/v1/', // VERIFY (append tracker)
                'refund' => '/order/v1/refund', // VERIFY
            ],
        ],

        'nayapay' => [
            'merchant_id' => env('NAYAPAY_MERCHANT_ID'),
            'api_key' => env('NAYAPAY_API_KEY'),
            'hash_key' => env('NAYAPAY_HASH_KEY'),
            'return_url' => env('NAYAPAY_RETURN_URL'),
            'currency' => env('NAYAPAY_CURRENCY', 'PKR'),

            'endpoints' => [
                // VERIFY all NayaPay (Arc) endpoints against your onboarding docs.
                'sandbox' => [
                    'checkout' => 'https://sandbox.nayapay.com/arc/checkout', // VERIFY
                    'status' => 'https://sandbox.nayapay.com/arc/order/status', // VERIFY
                ],
                'production' => [
                    'checkout' => 'https://api.nayapay.com/arc/checkout', // VERIFY
                    'status' => 'https://api.nayapay.com/arc/order/status', // VERIFY
                ],
            ],
        ],

        // PHASE 4 — scaffolds only. Not implemented yet (see drivers).
        'payfast' => [
            'merchant_id' => env('PAYFAST_MERCHANT_ID'),
            'secured_key' => env('PAYFAST_SECURED_KEY'),
            'return_url' => env('PAYFAST_RETURN_URL'),
            'currency' => env('PAYFAST_CURRENCY', 'PKR'),
            'endpoints' => [
                'sandbox' => [], // TODO
                'production' => [], // TODO
            ],
        ],

        'raast' => [
            // Raast is typically reached via an aggregator (e.g. PayFast SBP).
            'merchant_id' => env('RAAST_MERCHANT_ID'),
            'secret' => env('RAAST_SECRET'),
            'return_url' => env('RAAST_RETURN_URL'),
            'currency' => env('RAAST_CURRENCY', 'PKR'),
            'endpoints' => [
                'sandbox' => [], // TODO
                'production' => [], // TODO
            ],
        ],

    ],

];
