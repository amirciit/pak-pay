<?php

declare(strict_types=1);

use PakPay\PakPay\Drivers\NayaPayDriver;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use Illuminate\Http\Request;

function nayapayDriver(): NayaPayDriver
{
    return new NayaPayDriver([
        'merchant_id' => 'NP-1',
        'api_key' => 'np_api_key',
        'hash_key' => 'np_hash_key_123',
        'return_url' => 'https://shop.test/return',
        'currency' => 'PKR',
        'endpoints' => [
            'sandbox' => ['checkout' => 'https://sandbox.nayapay.test/checkout', 'status' => 'https://sandbox.nayapay.test/status'],
            'production' => ['checkout' => 'https://live.nayapay.test/checkout', 'status' => 'https://live.nayapay.test/status'],
        ],
    ], 'sandbox');
}

it('computes the NayaPay signature to a known vector', function (): void {
    $sig = nayapayDriver()->computeSignature([
        'amount' => '15000',
        'order_id' => 'ORDER-1',
        'status' => 'paid',
        'transaction_id' => 'NP-TXN-9',
    ]);

    expect($sig)->toBe('565B0195B9EDDB066AA99E675848AEDA2281DBD6390022A2BD7B1348420C9078');
});

it('verifies a correctly-signed NayaPay return', function (): void {
    $driver = nayapayDriver();

    $payload = [
        'amount' => '15000',
        'order_id' => 'ORDER-1',
        'status' => 'paid',
        'transaction_id' => 'NP-TXN-9',
    ];
    $payload['signature'] = $driver->computeSignature($payload);

    $result = $driver->verifyCallback(Request::create('/cb', 'POST', $payload));

    expect($result->isPaid())->toBeTrue()
        ->and($result->transactionId)->toBe('NP-TXN-9')
        ->and($result->orderId)->toBe('ORDER-1');
});

it('throws on a tampered NayaPay return', function (): void {
    $driver = nayapayDriver();
    $payload = [
        'amount' => '15000',
        'order_id' => 'ORDER-1',
        'status' => 'paid',
        'transaction_id' => 'NP-TXN-9',
    ];
    $payload['signature'] = $driver->computeSignature($payload);
    $payload['amount'] = '999999';

    $driver->verifyCallback(Request::create('/cb', 'POST', $payload));
})->throws(SignatureVerificationException::class);
