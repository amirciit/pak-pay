<?php

declare(strict_types=1);

use PakPay\PakPay\Contracts\GatewayDriver;
use PakPay\PakPay\Drivers\AbstractGatewayDriver;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use PakPay\PakPay\Facades\PakPay;
use PakPay\PakPay\PakPayManager;
use Illuminate\Http\Request;

/**
 * A merchant-supplied driver for a gateway PakPay does not ship.
 */
final class AcmeBankDriver extends AbstractGatewayDriver
{
    public function name(): string
    {
        return 'acmebank';
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult(
            success: true,
            state: PaymentState::PAID,
            transactionId: 'ACME-1',
            orderId: $request->orderId,
            amount: $request->amount,
        );
    }
}

it('resolves a custom gateway registered with extend()', function (): void {
    app(PakPayManager::class)->extend('acmebank', static fn (): GatewayDriver => new AcmeBankDriver([], 'sandbox'));

    $driver = PakPay::gateway('acmebank');

    expect($driver)->toBeInstanceOf(AcmeBankDriver::class)
        ->and($driver->name())->toBe('acmebank');
});

it('lets a custom driver implement only the flows it supports', function (): void {
    app(PakPayManager::class)->extend('acmebank', static fn (): GatewayDriver => new AcmeBankDriver([], 'sandbox'));

    $result = PakPay::gateway('acmebank')->charge(new PaymentRequest(amount: 15000, orderId: 'ORDER-1'));

    expect($result->isPaid())->toBeTrue()
        ->and($result->transactionId)->toBe('ACME-1');
});

it('makes an unimplemented flow throw rather than fake a result', function (): void {
    app(PakPayManager::class)->extend('acmebank', static fn (): GatewayDriver => new AcmeBankDriver([], 'sandbox'));

    PakPay::gateway('acmebank')->verifyCallback(Request::create('/cb', 'POST'));
})->throws(UnsupportedFlowException::class);
