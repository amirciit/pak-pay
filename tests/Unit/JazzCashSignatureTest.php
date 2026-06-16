<?php

declare(strict_types=1);

use PakPay\PakPay\Drivers\JazzCashDriver;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use Illuminate\Http\Request;

/**
 * Build a driver with the same salt used to generate the known vector.
 */
function jazzcashDriver(): JazzCashDriver
{
    return new JazzCashDriver([
        'merchant_id' => 'MC1234',
        'password' => 'secret-pass',
        'integrity_salt' => 'abc123salt',
        'return_url' => 'https://shop.test/return',
        'currency' => 'PKR',
        'endpoints' => ['sandbox' => [], 'production' => []],
    ], 'sandbox');
}

it('computes the JazzCash secure hash to a known vector', function (): void {
    // Vector generated independently with the documented algorithm.
    $expected = '7C8307B36F33B19A02BE2A890179CADFAE3FEAD71E3001F1876ED53C9F941A50';

    $hash = jazzcashDriver()->secureHash([
        'pp_Amount' => '10000',
        'pp_BillReference' => 'ORDER-1',
        'pp_MerchantID' => 'MC1234',
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnRefNo' => 'T20260101120000ABCDEF',
    ]);

    expect($hash)->toBe($expected);
});

it('ignores non-pp_ fields and empty values when hashing', function (): void {
    $a = jazzcashDriver()->secureHash([
        'pp_Amount' => '10000',
        'pp_BillReference' => 'ORDER-1',
        'pp_MerchantID' => 'MC1234',
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnRefNo' => 'T20260101120000ABCDEF',
    ]);

    $b = jazzcashDriver()->secureHash([
        'pp_Amount' => '10000',
        'pp_BillReference' => 'ORDER-1',
        'pp_MerchantID' => 'MC1234',
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnRefNo' => 'T20260101120000ABCDEF',
        'pp_Empty' => '',          // skipped (empty)
        'extra_field' => 'noise',  // skipped (not pp_*)
    ]);

    expect($a)->toBe($b);
});

it('verifies a correctly-signed callback', function (): void {
    $driver = jazzcashDriver();

    $payload = [
        'pp_ResponseCode' => '000',
        'pp_TxnRefNo' => 'T20260101120000ABCDEF',
        'pp_BillReference' => 'ORDER-1',
        'pp_Amount' => '10000',
        'pp_ResponseMessage' => 'Success',
    ];
    $payload['pp_SecureHash'] = $driver->secureHash($payload);

    $result = $driver->verifyCallback(Request::create('/cb', 'POST', $payload));

    expect($result)->toBeInstanceOf(PaymentResult::class)
        ->and($result->isPaid())->toBeTrue()
        ->and($result->transactionId)->toBe('T20260101120000ABCDEF')
        ->and($result->orderId)->toBe('ORDER-1');
});

it('throws on a tampered callback signature', function (): void {
    $driver = jazzcashDriver();

    $payload = [
        'pp_ResponseCode' => '000',
        'pp_TxnRefNo' => 'T20260101120000ABCDEF',
        'pp_Amount' => '10000',
    ];
    $payload['pp_SecureHash'] = $driver->secureHash($payload);

    // Attacker bumps the amount after signing.
    $payload['pp_Amount'] = '999999';

    $driver->verifyCallback(Request::create('/cb', 'POST', $payload));
})->throws(SignatureVerificationException::class);

it('throws when the callback has no signature', function (): void {
    jazzcashDriver()->verifyCallback(Request::create('/cb', 'POST', [
        'pp_ResponseCode' => '000',
    ]));
})->throws(SignatureVerificationException::class);
