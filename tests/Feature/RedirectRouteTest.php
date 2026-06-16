<?php

declare(strict_types=1);

use PakPay\PakPay\Facades\PakPay;
use PakPay\PakPay\DTOs\PaymentRequest;

it('renders a self-submitting POST form for a valid token', function (): void {
    $redirect = PakPay::gateway('jazzcash')->checkout(
        new PaymentRequest(amount: 10000, orderId: 'ORDER-1')
    );

    $this->get($redirect->getTargetUrl())
        ->assertOk()
        ->assertSee('document.forms[0].submit()', false)
        ->assertSee('action="https://sandbox.jazzcash.test/checkout"', false)
        ->assertSee('name="pp_SecureHash"', false);
});

it('consumes the token once (no replay)', function (): void {
    $redirect = PakPay::gateway('jazzcash')->checkout(
        new PaymentRequest(amount: 10000, orderId: 'ORDER-1')
    );
    $url = $redirect->getTargetUrl();

    $this->get($url)->assertOk();
    // Second use: the one-time token is gone.
    $this->get($url)->assertStatus(410);
});

it('returns 410 for an unknown token', function (): void {
    $this->get('pakpay/redirect/does-not-exist')->assertStatus(410);
});
