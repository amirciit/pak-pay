<?php

declare(strict_types=1);

use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Facades\PakPay;
use Illuminate\Support\Facades\Http;

it('maps the shared NayaPay status vocabulary onto PaymentState', function (string $gatewayValue, string $expected): void {
    Http::fake([
        '*nayapay.test/status*' => Http::response(['status' => $gatewayValue], 200),
    ]);

    expect(PakPay::gateway('nayapay')->status('ORDER-1')->state)->toBe($expected);
})->with([
    ['paid', PaymentState::PAID],
    ['COMPLETED', PaymentState::PAID],
    ['successful', PaymentState::PAID],
    ['in_progress', PaymentState::PENDING],
    ['in progress', PaymentState::PENDING],
    ['initiated', PaymentState::PENDING],
    ['processing', PaymentState::PROCESSING],
    ['declined', PaymentState::FAILED],
    ['rejected', PaymentState::FAILED],
    ['canceled', PaymentState::CANCELLED],
    ['refunded', PaymentState::REFUNDED],
    ['something-else', PaymentState::UNKNOWN],
]);

it('strips the Safepay tracker prefix before mapping', function (string $gatewayValue, string $expected): void {
    Http::fake([
        '*client/passport*' => Http::response(['data' => ['token' => 'TKN1']], 200),
        '*order/v1/*' => Http::response(['data' => ['state' => $gatewayValue]], 200),
    ]);

    expect(PakPay::gateway('safepay')->status('track_1')->state)->toBe($expected);
})->with([
    ['TRACKER_COMPLETED', PaymentState::PAID],
    ['TRACKER_PENDING', PaymentState::PENDING],
    ['TRACKER_REJECTED', PaymentState::FAILED],
    ['TRACKER_CANCELLED', PaymentState::CANCELLED],
    ['payment:created', PaymentState::UNKNOWN],
]);

it('treats an unmapped JazzCash status with a response code as failed', function (): void {
    Http::fake([
        'sandbox.jazzcash.test/status' => Http::response([
            'pp_ResponseCode' => '124',
            'pp_Status' => 'Something JazzCash invented',
        ], 200),
    ]);

    expect(PakPay::gateway('jazzcash')->status('T123')->state)->toBe(PaymentState::FAILED);
});

it('reports the HTTP status when a gateway answers with something other than JSON', function (): void {
    Http::fake([
        'sandbox.jazzcash.test/status' => Http::response('<html>502 Bad Gateway</html>', 502),
    ]);

    PakPay::gateway('jazzcash')->status('T123');
})->throws(\PakPay\PakPay\Exceptions\GatewayException::class, 'code: 502');
