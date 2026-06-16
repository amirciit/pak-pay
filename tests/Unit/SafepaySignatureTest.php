<?php

declare(strict_types=1);

use PakPay\PakPay\Drivers\SafepayDriver;

function safepayDriver(): SafepayDriver
{
    return new SafepayDriver([
        'client_key' => 'sfp_pub_test',
        'secret_key' => 'sfp_secret_test',
        'webhook_secret' => 'whsec_test',
        'return_url' => 'https://shop.test/return',
        'currency' => 'PKR',
        'endpoints' => [
            'sandbox' => ['api' => 'https://sandbox.api.safepay.test', 'checkout' => 'https://c.test/pay', 'environment' => 'sandbox'],
            'production' => ['api' => 'https://api.safepay.test', 'checkout' => 'https://c.test/pay', 'environment' => 'production'],
        ],
        'paths' => ['passport' => '/p', 'order' => '/o', 'status' => '/s/', 'refund' => '/r'],
    ], 'sandbox');
}

it('computes the Safepay webhook signature to a known vector', function (): void {
    $body = '{"type":"payment:created","data":{"state":"TRACKER_COMPLETED","tracker":"track_123","order_id":"ORDER-1","amount":15000}}';

    expect(safepayDriver()->computeWebhookSignature($body))
        ->toBe('8a361c79148a23f04101a86d59161d1d69ac3815a9f190fe11828816ffb6ac44');
});

it('produces a different signature for tampered bodies', function (): void {
    $a = safepayDriver()->computeWebhookSignature('{"amount":15000}');
    $b = safepayDriver()->computeWebhookSignature('{"amount":999999}');

    expect($a)->not->toBe($b);
});
