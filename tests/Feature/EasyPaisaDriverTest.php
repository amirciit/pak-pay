<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\Customer;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use PakPay\PakPay\Facades\PakPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function easypaisaRequest(): PaymentRequest
{
    return new PaymentRequest(
        amount: 15000,
        orderId: 'ORDER-1',
        customer: new Customer(mobile: '03019680302', email: 'ali@shop.test'),
    );
}

it('checkout stashes signed fields and redirects', function (): void {
    $response = PakPay::gateway('easypaisa')->checkout(easypaisaRequest());

    expect($response)->toBeInstanceOf(RedirectResponse::class);

    $token = basename(parse_url($response->getTargetUrl(), PHP_URL_PATH));
    $stash = Cache::get("pakpay:redirect:{$token}");

    expect($stash['action'])->toBe('https://sandbox.easypaisa.test/checkout')
        ->and($stash['fields'])->toHaveKey('merchantHashedReq')
        ->and($stash['fields']['storeId'])->toBe('store-99');
});

it('initiates a mobile account charge', function (): void {
    Http::fake([
        'sandbox.easypaisa.test/ma' => Http::response([
            'responseCode' => '0000',
            'responseDesc' => 'Success',
            'transactionId' => 'TXN-9',
        ], 200),
    ]);

    $result = PakPay::gateway('easypaisa')->charge(easypaisaRequest());

    expect($result->isPaid())->toBeTrue()
        ->and($result->state)->toBe(PaymentState::PAID)
        ->and($result->transactionId)->toBe('TXN-9');
});

it('does not fake refund (unsupported flow)', function (): void {
    PakPay::gateway('easypaisa')->refund('TXN-9', 5000);
})->throws(UnsupportedFlowException::class);
