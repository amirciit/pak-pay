<?php

declare(strict_types=1);

use PakPay\PakPay\Drivers\EasyPaisaDriver;
use PakPay\PakPay\Drivers\JazzCashDriver;
use PakPay\PakPay\Facades\PakPay;
use PakPay\PakPay\PakPayManager;

it('resolves the configured default gateway', function (): void {
    expect(PakPay::gateway())->toBeInstanceOf(JazzCashDriver::class);
});

it('resolves a named gateway', function (): void {
    expect(PakPay::gateway('easypaisa'))->toBeInstanceOf(EasyPaisaDriver::class);
    expect(PakPay::gateway('jazzcash'))->toBeInstanceOf(JazzCashDriver::class);
});

it('caches resolved driver instances', function (): void {
    $a = PakPay::gateway('jazzcash');
    $b = PakPay::gateway('jazzcash');

    expect($a)->toBe($b);
});

it('binds the manager as a singleton', function (): void {
    expect(app(PakPayManager::class))->toBe(app(PakPayManager::class));
});

it('throws for an unknown gateway', function (): void {
    PakPay::gateway('paypal');
})->throws(InvalidArgumentException::class);
