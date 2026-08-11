<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\Facades\PakPay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Run a hosted checkout and report how many minutes ahead of "now" it asked
 * the cache to keep the stashed payload.
 */
function capturedRedirectTtl(): int
{
    $captured = null;

    Cache::shouldReceive('put')
        ->once()
        ->andReturnUsing(function (string $key, array $value, $expiry) use (&$captured): bool {
            expect($key)->toStartWith('pakpay:redirect:');
            $captured = $expiry;

            return true;
        });

    PakPay::gateway('jazzcash')->checkout(new PaymentRequest(amount: 10000, orderId: 'ORDER-1'));

    expect($captured)->toBeInstanceOf(Carbon::class);

    // Carbon 2 returns an int here, Carbon 3 a float — normalise both.
    return (int) round((float) Carbon::now()->diffInMinutes($captured));
}

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('stashes the payload for ten minutes by default', function (): void {
    expect(capturedRedirectTtl())->toBe(10);
});

it('honours a configured redirect ttl', function (): void {
    config()->set('pakpay.redirect_ttl', 3);

    expect(capturedRedirectTtl())->toBe(3);
});

it('falls back to the default for a nonsensical ttl', function (int $ttl): void {
    config()->set('pakpay.redirect_ttl', $ttl);

    expect(capturedRedirectTtl())->toBe(10);
})->with([0, -5]);

it('sends no-store headers with the rendered redirect form', function (): void {
    $redirect = PakPay::gateway('jazzcash')->checkout(
        new PaymentRequest(amount: 10000, orderId: 'ORDER-1')
    );

    $response = $this->get($redirect->getTargetUrl())->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});
