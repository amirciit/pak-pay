<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use PakPay\PakPay\Facades\PakPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('builds a signed hosted checkout and redirects', function (): void {
    $response = PakPay::gateway('nayapay')->checkout(
        new PaymentRequest(amount: 15000, orderId: 'ORDER-1')
    );

    expect($response)->toBeInstanceOf(RedirectResponse::class);

    $token = basename(parse_url($response->getTargetUrl(), PHP_URL_PATH));
    $stash = Cache::get("pakpay:redirect:{$token}");

    expect($stash['action'])->toBe('https://sandbox.nayapay.test/checkout')
        ->and($stash['fields'])->toHaveKey('signature')
        ->and($stash['fields']['merchant_id'])->toBe('NP-1');
});

it('does not implement the direct card API (PCI)', function (): void {
    PakPay::gateway('nayapay')->charge(new PaymentRequest(amount: 15000, orderId: 'ORDER-1'));
})->throws(UnsupportedFlowException::class);

it('queries status', function (): void {
    Http::fake([
        '*nayapay.test/status*' => Http::response(['status' => 'paid', 'amount' => 15000], 200),
    ]);

    $status = PakPay::gateway('nayapay')->status('ORDER-1');

    expect($status->isPaid())->toBeTrue()
        ->and($status->transactionId)->toBe('ORDER-1');
});
