<?php

declare(strict_types=1);

use PakPay\PakPay\Drivers\EasyPaisaDriver;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use Illuminate\Http\Request;

function easypaisaDriver(): EasyPaisaDriver
{
    return new EasyPaisaDriver([
        'store_id' => 'store-99',
        'hash_key' => '1234567890123456',
        'account_num' => '03019680302',
        'return_url' => 'https://shop.test/return',
        'endpoints' => ['sandbox' => [], 'production' => []],
    ], 'sandbox');
}

it('computes the EasyPaisa merchantHashedReq (AES-128-ECB) to a known vector', function (): void {
    $expected = 'c1Oq5ouR1KaNqQ2n4MEe1VMLXlliXrN1csCmG+OsC1I5qvqIMvq4qXKNessXOOdYzB15DxxoFtXt6FgZRyYXtVOAc4FbcwzHektLeKB/VdgGRUT/NdbRpzOsu07Uxwe1r8+fw3KZNziac9QfDMlCCIK+u2QZCW0TnPzQRKqGRlA=';

    $hash = easypaisaDriver()->merchantHashedReq([
        'amount' => '150.00',
        'orderRefNum' => 'ORDER-1',
        'paymentMethod' => 'InitialRequest',
        'postBackURL' => 'https://shop.test/return',
        'storeId' => 'store-99',
    ]);

    expect($hash)->toBe($expected);
});

it('computes the EasyPaisa return signature to a known vector', function (): void {
    $expected = 'EAD81F51063EB899964FD3211CAA0FFE3BBC0AA2AF6F6D2379A12CF5BBE707F3';

    $sig = easypaisaDriver()->expectedReturnSignature([
        'amount' => '150.00',
        'orderRefNum' => 'ORDER-1',
        'status' => '0000',
        'transactionId' => 'TXN-555',
    ]);

    expect($sig)->toBe($expected);
});

it('verifies a correctly-signed EasyPaisa return', function (): void {
    $driver = easypaisaDriver();

    $payload = [
        'amount' => '150.00',
        'orderRefNum' => 'ORDER-1',
        'status' => '0000',
        'transactionId' => 'TXN-555',
    ];
    $payload['signature'] = $driver->expectedReturnSignature($payload);

    $result = $driver->verifyCallback(Request::create('/cb', 'POST', $payload));

    expect($result->isPaid())->toBeTrue()
        ->and($result->transactionId)->toBe('TXN-555')
        ->and($result->orderId)->toBe('ORDER-1');
});

it('throws on a tampered EasyPaisa return', function (): void {
    $driver = easypaisaDriver();

    $payload = [
        'amount' => '150.00',
        'orderRefNum' => 'ORDER-1',
        'status' => '0000',
        'transactionId' => 'TXN-555',
    ];
    $payload['signature'] = $driver->expectedReturnSignature($payload);
    $payload['amount'] = '999999.00';

    $driver->verifyCallback(Request::create('/cb', 'POST', $payload));
})->throws(SignatureVerificationException::class);
