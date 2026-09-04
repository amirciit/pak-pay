<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\Customer;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Facades\PakPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function jazzcashRequest(): PaymentRequest
{
    return new PaymentRequest(
        amount: 10000,
        orderId: 'ORDER-1',
        description: 'Test order',
        customer: new Customer(name: 'Ali', mobile: '03019680302', cnicLast6: '123456'),
    );
}

it('checkout stashes a signed payload and redirects to the render route', function (): void {
    $response = PakPay::gateway('jazzcash')->checkout(jazzcashRequest());

    expect($response)->toBeInstanceOf(RedirectResponse::class);

    // The redirect points at the package route with a one-time token.
    $location = $response->getTargetUrl();
    expect($location)->toContain('pakpay/redirect/');

    $token = basename(parse_url($location, PHP_URL_PATH));
    $stash = Cache::get("pakpay:redirect:{$token}");

    expect($stash)->toBeArray()
        ->and($stash['action'])->toBe('https://sandbox.jazzcash.test/checkout')
        ->and($stash['fields'])->toHaveKey('pp_SecureHash')
        ->and($stash['fields']['pp_Amount'])->toBe('10000');
});

it('omits the merchant password from the hosted form when disabled', function (): void {
    config()->set('pakpay.gateways.jazzcash.hosted_send_password', false);

    $response = PakPay::gateway('jazzcash')->checkout(jazzcashRequest());

    $token = basename(parse_url($response->getTargetUrl(), PHP_URL_PATH));
    $stash = Cache::get("pakpay:redirect:{$token}");

    expect($stash['fields'])->not->toHaveKey('pp_Password')
        ->and($stash['fields'])->toHaveKey('pp_SecureHash');
});

it('omits the merchant password from the hosted form by default', function (): void {
    // Secure default: pp_Password is NOT rendered into the browser form unless
    // hosted_send_password is explicitly enabled.
    $response = PakPay::gateway('jazzcash')->checkout(jazzcashRequest());

    $token = basename(parse_url($response->getTargetUrl(), PHP_URL_PATH));
    $stash = Cache::get("pakpay:redirect:{$token}");

    expect($stash['fields'])->not->toHaveKey('pp_Password');
});

it('includes the merchant password in the hosted form when explicitly enabled', function (): void {
    config()->set('pakpay.gateways.jazzcash.hosted_send_password', true);

    $response = PakPay::gateway('jazzcash')->checkout(jazzcashRequest());

    $token = basename(parse_url($response->getTargetUrl(), PHP_URL_PATH));
    $stash = Cache::get("pakpay:redirect:{$token}");

    expect($stash['fields'])->toHaveKey('pp_Password');
});

it('honours a configured transaction expiry window', function (): void {
    config()->set('pakpay.gateways.jazzcash.txn_expiry_minutes', 15);

    $response = PakPay::gateway('jazzcash')->checkout(jazzcashRequest());

    $token = basename(parse_url($response->getTargetUrl(), PHP_URL_PATH));
    $fields = Cache::get("pakpay:redirect:{$token}")['fields'];

    $start = DateTimeImmutable::createFromFormat('YmdHis', $fields['pp_TxnDateTime']);
    $expiry = DateTimeImmutable::createFromFormat('YmdHis', $fields['pp_TxnExpiryDateTime']);

    expect(intdiv($expiry->getTimestamp() - $start->getTimestamp(), 60))->toBe(15);
});

it('charges a wallet successfully', function (): void {
    Http::fake([
        'sandbox.jazzcash.test/wallet' => Http::response([
            'pp_ResponseCode' => '000',
            'pp_ResponseMessage' => 'Success',
            'pp_TxnRefNo' => 'T123',
            'pp_Amount' => '10000',
        ], 200),
    ]);

    $result = PakPay::gateway('jazzcash')->charge(jazzcashRequest());

    expect($result->isPaid())->toBeTrue()
        ->and($result->state)->toBe(PaymentState::PAID)
        ->and($result->transactionId)->toBe('T123');

    Http::assertSent(fn ($request) => isset($request['pp_CNIC']) && $request['pp_CNIC'] === '123456'
        && isset($request['pp_SecureHash']));
});

it('reports a failed wallet charge', function (): void {
    Http::fake([
        'sandbox.jazzcash.test/wallet' => Http::response([
            'pp_ResponseCode' => '124',
            'pp_ResponseMessage' => 'Insufficient funds',
        ], 200),
    ]);

    $result = PakPay::gateway('jazzcash')->charge(jazzcashRequest());

    expect($result->success)->toBeFalse()
        ->and($result->state)->toBe(PaymentState::FAILED)
        ->and($result->gatewayCode)->toBe('124');
});

it('requires CNIC for a wallet charge', function (): void {
    $request = new PaymentRequest(
        amount: 10000,
        orderId: 'ORDER-2',
        customer: new Customer(mobile: '03019680302'), // no CNIC
    );

    PakPay::gateway('jazzcash')->charge($request);
})->throws(\PakPay\PakPay\Exceptions\GatewayException::class);

it('queries transaction status', function (): void {
    Http::fake([
        'sandbox.jazzcash.test/status' => Http::response([
            'pp_ResponseCode' => '000',
            'pp_Amount' => '10000',
            'pp_Status' => 'Completed',
        ], 200),
    ]);

    $status = PakPay::gateway('jazzcash')->status('T123');

    expect($status->isPaid())->toBeTrue()
        ->and($status->transactionId)->toBe('T123');
});

it('refunds a transaction', function (): void {
    Http::fake([
        'sandbox.jazzcash.test/refund' => Http::response([
            'pp_ResponseCode' => '000',
            'pp_RetreivalReferenceNo' => 'RRN-1',
        ], 200),
    ]);

    $refund = PakPay::gateway('jazzcash')->refund('T123', 5000);

    expect($refund->success)->toBeTrue()
        ->and($refund->refundId)->toBe('RRN-1')
        ->and($refund->amount)->toBe(5000);
});
