<?php

declare(strict_types=1);

use PakPay\PakPay\Drivers\PayFastDriver;
use PakPay\PakPay\Drivers\RaastDriver;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use PakPay\PakPay\Facades\PakPay;

it('resolves the PayFast and Raast scaffolds', function (): void {
    expect(PakPay::gateway('payfast'))->toBeInstanceOf(PayFastDriver::class);
    expect(PakPay::gateway('raast'))->toBeInstanceOf(RaastDriver::class);
});

it('PayFast scaffold throws UnsupportedFlow for every flow', function (string $flow): void {
    $driver = PakPay::gateway('payfast');
    $req = new PaymentRequest(amount: 15000, orderId: 'ORDER-1');

    match ($flow) {
        'checkout' => $driver->checkout($req),
        'charge' => $driver->charge($req),
        'refund' => $driver->refund('T1', 100),
        'status' => $driver->status('T1'),
    };
})->with(['checkout', 'charge', 'refund', 'status'])->throws(UnsupportedFlowException::class);

it('Raast scaffold throws UnsupportedFlow for checkout', function (): void {
    PakPay::gateway('raast')->checkout(new PaymentRequest(amount: 15000, orderId: 'ORDER-1'));
})->throws(UnsupportedFlowException::class);
