<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use PakPay\PakPay\Facades\PakPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function safepayRequest(): PaymentRequest
{
    return new PaymentRequest(amount: 15000, orderId: 'ORDER-1');
}

it('initialises an order and redirects to hosted checkout', function (): void {
    Http::fake([
        '*client/passport*' => Http::response(['data' => ['token' => 'TKN1']], 200),
        '*order/v1/init*' => Http::response(['data' => ['tracker' => 'track_1']], 200),
    ]);

    $response = PakPay::gateway('safepay')->checkout(safepayRequest());

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())
        ->toContain('beacon=track_1')
        ->toContain('env=sandbox');
});

it('regenerates the passport token on a 401 and retries', function (): void {
    Http::fake([
        '*client/passport*' => Http::sequence()
            ->push(['data' => ['token' => 'TKN1']], 200)
            ->push(['data' => ['token' => 'TKN2']], 200),
        '*order/v1/init*' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['data' => ['tracker' => 'track_after_retry']], 200),
    ]);

    $response = PakPay::gateway('safepay')->checkout(safepayRequest());

    // Success only if the driver re-fetched the token and retried the order.
    expect($response->getTargetUrl())->toContain('beacon=track_after_retry');
});

it('returns Smart Payment Button data', function (): void {
    Http::fake([
        '*client/passport*' => Http::response(['data' => ['token' => 'TKN1']], 200),
        '*order/v1/init*' => Http::response(['data' => ['tracker' => 'track_btn']], 200),
    ]);

    $data = PakPay::gateway('safepay')->smartButtonData(safepayRequest());

    expect($data['environment'])->toBe('sandbox')
        ->and($data['clientKey'])->toBe('sfp_pub_test')
        ->and($data['tracker'])->toBe('track_btn')
        ->and($data['orderId'])->toBe('ORDER-1');
});

it('does not accept raw card charges (PCI)', function (): void {
    PakPay::gateway('safepay')->charge(safepayRequest());
})->throws(UnsupportedFlowException::class);

it('verifies a genuine webhook', function (): void {
    $driver = PakPay::gateway('safepay');
    $body = '{"type":"payment:created","data":{"state":"TRACKER_COMPLETED","tracker":"track_123","order_id":"ORDER-1","amount":15000}}';
    $sig = $driver->computeWebhookSignature($body);

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_SFPY_SIGNATURE' => $sig,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $result = $driver->verifyCallback($request);

    expect($result->isPaid())->toBeTrue()
        ->and($result->transactionId)->toBe('track_123')
        ->and($result->orderId)->toBe('ORDER-1');
});

it('rejects a tampered webhook', function (): void {
    $driver = PakPay::gateway('safepay');
    $body = '{"data":{"state":"TRACKER_COMPLETED","amount":15000}}';
    $sig = $driver->computeWebhookSignature($body);

    // Body changed after signing.
    $tampered = '{"data":{"state":"TRACKER_COMPLETED","amount":999999}}';
    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_SFPY_SIGNATURE' => $sig,
        'CONTENT_TYPE' => 'application/json',
    ], $tampered);

    $driver->verifyCallback($request);
})->throws(SignatureVerificationException::class);

it('rejects a webhook with no signature', function (): void {
    $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"data":{}}');

    PakPay::gateway('safepay')->verifyCallback($request);
})->throws(SignatureVerificationException::class);

it('rejects a replayed webhook (duplicate signature) even without a timestamp', function (): void {
    $driver = PakPay::gateway('safepay');
    $body = '{"type":"payment:created","data":{"state":"TRACKER_COMPLETED","tracker":"track_dedupe","order_id":"ORDER-9","amount":15000}}';
    $sig = $driver->computeWebhookSignature($body);

    $makeRequest = fn (): Request => Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_SFPY_SIGNATURE' => $sig,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    // First delivery is accepted (no timestamp header present).
    $result = $driver->verifyCallback($makeRequest());
    expect($result->isPaid())->toBeTrue();

    // Re-delivering the identical, validly-signed webhook is rejected as a replay.
    $driver->verifyCallback($makeRequest());
})->throws(SignatureVerificationException::class);

it('still accepts a different webhook after one has been recorded', function (): void {
    $driver = PakPay::gateway('safepay');

    $deliver = function (string $body) use ($driver) {
        return $driver->verifyCallback(Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SFPY_SIGNATURE' => $driver->computeWebhookSignature($body),
            'CONTENT_TYPE' => 'application/json',
        ], $body));
    };

    $first = $deliver('{"data":{"state":"TRACKER_COMPLETED","tracker":"t1","order_id":"ORDER-1"}}');
    $second = $deliver('{"data":{"state":"TRACKER_COMPLETED","tracker":"t2","order_id":"ORDER-2"}}');

    expect($first->orderId)->toBe('ORDER-1')
        ->and($second->orderId)->toBe('ORDER-2');
});

it('rejects a replayed webhook (stale timestamp)', function (): void {
    $driver = PakPay::gateway('safepay');
    $body = '{"data":{"state":"TRACKER_COMPLETED"}}';
    $sig = $driver->computeWebhookSignature($body);

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X_SFPY_SIGNATURE' => $sig,
        'HTTP_X_SFPY_TIMESTAMP' => (string) (time() - 6000), // way outside the window
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $driver->verifyCallback($request);
})->throws(SignatureVerificationException::class);

it('queries order status', function (): void {
    Http::fake([
        '*client/passport*' => Http::response(['data' => ['token' => 'TKN1']], 200),
        '*order/v1/*' => Http::response(['data' => ['state' => 'TRACKER_COMPLETED', 'amount' => 15000]], 200),
    ]);

    $status = PakPay::gateway('safepay')->status('track_1');

    expect($status->isPaid())->toBeTrue()
        ->and($status->transactionId)->toBe('track_1');
});

it('refunds an order', function (): void {
    Http::fake([
        '*client/passport*' => Http::response(['data' => ['token' => 'TKN1']], 200),
        '*order/v1/refund*' => Http::response(['data' => ['refund_id' => 'rf_1']], 200),
    ]);

    $refund = PakPay::gateway('safepay')->refund('track_1', 5000);

    expect($refund->success)->toBeTrue()
        ->and($refund->refundId)->toBe('rf_1')
        ->and($refund->amount)->toBe(5000);
});

it('does not report a rejected refund as successful', function (): void {
    Http::fake([
        '*client/passport*' => Http::response(['data' => ['token' => 'TKN1']], 200),
        // Accepted by the API (200) but rejected in the payload.
        '*order/v1/refund*' => Http::response(['data' => ['state' => 'REJECTED', 'message' => 'Not refundable']], 200),
    ]);

    $refund = PakPay::gateway('safepay')->refund('track_1', 5000);

    expect($refund->success)->toBeFalse()
        ->and($refund->gatewayCode)->toBe('REJECTED')
        ->and($refund->message)->toBe('Not refundable');
});
