<?php

declare(strict_types=1);

use PakPay\PakPay\Enums\PaymentState;

it('treats only PAID as successful', function (): void {
    expect(PaymentState::isSuccessful(PaymentState::PAID))->toBeTrue();

    foreach (array_diff(PaymentState::all(), [PaymentState::PAID]) as $state) {
        expect(PaymentState::isSuccessful($state))->toBeFalse();
    }
});

it('knows which states are final', function (string $state, bool $final): void {
    expect(PaymentState::isFinal($state))->toBe($final);
})->with([
    [PaymentState::PENDING, false],
    [PaymentState::PROCESSING, false],
    [PaymentState::UNKNOWN, false],
    [PaymentState::PAID, true],
    [PaymentState::FAILED, true],
    [PaymentState::CANCELLED, true],
    [PaymentState::REFUNDED, true],
]);

it('exposes every state as a unique lowercase string', function (): void {
    $states = PaymentState::all();

    expect($states)->toHaveCount(7)
        ->and($states)->toBe(array_unique($states));

    foreach ($states as $state) {
        expect($state)->toBe(strtolower($state));
    }
});
