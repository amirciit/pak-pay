<?php

declare(strict_types=1);

namespace PakPay\PakPay\Tests;

use PakPay\PakPay\PakPayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case wiring the package into a Testbench Laravel app.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Register the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PakPayServiceProvider::class,
        ];
    }

    /**
     * Provide deterministic gateway config for tests.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('pakpay.default', 'jazzcash');
        $app['config']->set('pakpay.mode', 'sandbox');

        $app['config']->set('pakpay.gateways.jazzcash', [
            'merchant_id' => 'MC1234',
            'password' => 'secret-pass',
            'integrity_salt' => 'abc123salt',
            'return_url' => 'https://shop.test/jazzcash/return',
            'currency' => 'PKR',
            'language' => 'EN',
            'endpoints' => [
                'sandbox' => [
                    'checkout' => 'https://sandbox.jazzcash.test/checkout',
                    'wallet' => 'https://sandbox.jazzcash.test/wallet',
                    'status' => 'https://sandbox.jazzcash.test/status',
                    'refund' => 'https://sandbox.jazzcash.test/refund',
                ],
                'production' => [
                    'checkout' => 'https://live.jazzcash.test/checkout',
                    'wallet' => 'https://live.jazzcash.test/wallet',
                    'status' => 'https://live.jazzcash.test/status',
                    'refund' => 'https://live.jazzcash.test/refund',
                ],
            ],
        ]);

        $app['config']->set('pakpay.gateways.easypaisa', [
            'store_id' => 'store-99',
            'hash_key' => '1234567890123456',
            'account_num' => '03019680302',
            'return_url' => 'https://shop.test/easypaisa/return',
            'endpoints' => [
                'sandbox' => [
                    'checkout' => 'https://sandbox.easypaisa.test/checkout',
                    'confirm' => 'https://sandbox.easypaisa.test/confirm',
                    'mobile_account' => 'https://sandbox.easypaisa.test/ma',
                    'status' => 'https://sandbox.easypaisa.test/status',
                ],
                'production' => [
                    'checkout' => 'https://live.easypaisa.test/checkout',
                    'confirm' => 'https://live.easypaisa.test/confirm',
                    'mobile_account' => 'https://live.easypaisa.test/ma',
                    'status' => 'https://live.easypaisa.test/status',
                ],
            ],
        ]);

        $app['config']->set('pakpay.gateways.safepay', [
            'client_key' => 'sfp_pub_test',
            'secret_key' => 'sfp_secret_test',
            'webhook_secret' => 'whsec_test',
            'return_url' => 'https://shop.test/safepay/return',
            'cancel_url' => 'https://shop.test/safepay/cancel',
            'currency' => 'PKR',
            'endpoints' => [
                'sandbox' => [
                    'api' => 'https://sandbox.api.safepay.test',
                    'checkout' => 'https://sandbox.checkout.safepay.test/pay',
                    'environment' => 'sandbox',
                ],
                'production' => [
                    'api' => 'https://api.safepay.test',
                    'checkout' => 'https://checkout.safepay.test/pay',
                    'environment' => 'production',
                ],
            ],
            'paths' => [
                'passport' => '/client/passport/v1/token',
                'order' => '/order/v1/init',
                'status' => '/order/v1/',
                'refund' => '/order/v1/refund',
            ],
        ]);

        $app['config']->set('pakpay.gateways.nayapay', [
            'merchant_id' => 'NP-1',
            'api_key' => 'np_api_key',
            'hash_key' => 'np_hash_key_123',
            'return_url' => 'https://shop.test/nayapay/return',
            'currency' => 'PKR',
            'endpoints' => [
                'sandbox' => [
                    'checkout' => 'https://sandbox.nayapay.test/checkout',
                    'status' => 'https://sandbox.nayapay.test/status',
                ],
                'production' => [
                    'checkout' => 'https://live.nayapay.test/checkout',
                    'status' => 'https://live.nayapay.test/status',
                ],
            ],
        ]);

        $app['config']->set('pakpay.gateways.payfast', [
            'merchant_id' => 'PF-1',
            'secured_key' => 'pf_secret',
            'return_url' => 'https://shop.test/payfast/return',
            'currency' => 'PKR',
            'endpoints' => ['sandbox' => [], 'production' => []],
        ]);

        $app['config']->set('pakpay.gateways.raast', [
            'merchant_id' => 'RA-1',
            'secret' => 'ra_secret',
            'return_url' => 'https://shop.test/raast/return',
            'currency' => 'PKR',
            'endpoints' => ['sandbox' => [], 'production' => []],
        ]);
    }
}
