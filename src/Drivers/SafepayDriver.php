<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Exceptions\GatewayException;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Safepay (PSP) gateway driver — Advanced Checkout (REST) + Smart Button.
 *
 * Flow:
 *  1. Obtain a passport (time-based) token at /client/passport/v1/token.
 *     The token is valid ~1 hour; it is cached and regenerated on a 401.
 *  2. Initialise an order/payment session via the Order API.
 *  3. Redirect the customer to the hosted checkout (or hand the tracker to the
 *     drop-in Smart Payment Button via {@see smartButtonData()}).
 *
 * Security: webhooks are verified with an HMAC-SHA256 signature over the raw
 * request body, compared in constant time (hash_equals).
 *
 * @see https://api.getsafepay.com  // VERIFY Order API paths + webhook header against official docs.
 */
final class SafepayDriver extends AbstractGatewayDriver
{
    /** Webhook signature header. // VERIFY exact header name with Safepay. */
    private const SIGNATURE_HEADER = 'X-SFPY-Signature';

    /** Optional webhook timestamp header used for replay protection. // VERIFY. */
    private const TIMESTAMP_HEADER = 'X-SFPY-Timestamp';

    /** Max age (seconds) of a webhook before it is stale (when a timestamp is sent). */
    private const REPLAY_WINDOW = 300;

    /** How long (seconds) each seen signature is remembered, to reject duplicates. */
    private const DEDUP_TTL = 86400;

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'safepay';
    }

    /**
     * {@inheritDoc}
     *
     * Initialises an order and redirects to the hosted Safepay checkout.
     */
    public function checkout(PaymentRequest $request): RedirectResponse
    {
        $tracker = $this->initOrder($request);

        $params = http_build_query(array_filter([
            'beacon' => $tracker,
            'env' => $this->endpoint('environment'),
            'source' => 'mobile',
            'redirect_url' => $request->returnUrl ?? $this->config('return_url'),
            'cancel_url' => $this->config('cancel_url'),
            'order_id' => $request->orderId,
        ], static fn ($v): bool => $v !== null && $v !== ''));

        // VERIFY the exact hosted-checkout URL + query parameters with Safepay.
        return new RedirectResponse($this->endpoint('checkout') . '?' . $params);
    }

    /**
     * Data required by the drop-in Smart Payment Button (JS) on the frontend.
     *
     * @return array<string, string|null>
     */
    public function smartButtonData(PaymentRequest $request): array
    {
        return [
            'environment' => $this->endpoint('environment'),
            'clientKey' => $this->requireConfig('client_key'),
            'tracker' => $this->initOrder($request),
            'orderId' => $request->orderId,
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Safepay does not accept raw card data on the merchant server (PCI).
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        throw UnsupportedFlowException::make($this->name(), 'charge');
    }

    /**
     * {@inheritDoc}
     *
     * Verifies a Safepay webhook: HMAC-SHA256 over the raw body, constant-time
     * comparison, plus a timestamp replay window when the header is present.
     */
    public function verifyCallback(Request $request): PaymentResult
    {
        $raw = $request->getContent();
        $provided = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($provided === '') {
            throw SignatureVerificationException::missing($this->name());
        }

        $expected = $this->computeWebhookSignature($raw);

        if (! hash_equals($expected, $provided)) {
            throw SignatureVerificationException::mismatch($this->name());
        }

        $this->assertNotReplayed($request, $provided);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true) ?: [];

        $statusValue = (string) (Arr::get($payload, 'data.state')
            ?? Arr::get($payload, 'data.status')
            ?? Arr::get($payload, 'type')
            ?? '');

        $state = $this->mapState($statusValue);

        return new PaymentResult(
            success: PaymentState::isSuccessful($state),
            state: $state,
            transactionId: (string) (Arr::get($payload, 'data.tracker') ?? Arr::get($payload, 'data.token') ?? '') ?: null,
            orderId: Arr::get($payload, 'data.order_id') !== null ? (string) Arr::get($payload, 'data.order_id') : null,
            amount: Arr::get($payload, 'data.amount') !== null ? (int) Arr::get($payload, 'data.amount') : null,
            gatewayCode: $statusValue !== '' ? $statusValue : null,
            message: Arr::get($payload, 'data.message') !== null ? (string) Arr::get($payload, 'data.message') : null,
            raw: $payload,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $txnId, int $amount): RefundResult
    {
        $response = $this->withPassport(fn (string $token): HttpResponse => Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->apiUrl($this->path('refund')), [
                'tracker' => $txnId,
                'amount' => $amount,
            ]));

        if (! $response->successful()) {
            throw GatewayException::fromResponse($this->name(), 'Refund request failed.', (string) $response->status());
        }

        $data = (array) $response->json();

        // A 2xx means Safepay accepted the refund request; if the body also
        // carries a state, honour it — an accepted-but-rejected refund is not
        // a success, and reporting it as one would strand the customer.
        $stateValue = (string) (Arr::get($data, 'data.state') ?? Arr::get($data, 'data.status') ?? '');
        $state = $stateValue === '' ? null : $this->mapState($stateValue);

        return new RefundResult(
            success: $state === null || $state !== PaymentState::FAILED && $state !== PaymentState::CANCELLED,
            transactionId: $txnId,
            refundId: Arr::get($data, 'data.refund_id') !== null ? (string) Arr::get($data, 'data.refund_id') : null,
            amount: $amount,
            gatewayCode: $stateValue !== '' ? $stateValue : (string) $response->status(),
            message: Arr::get($data, 'data.message') !== null ? (string) Arr::get($data, 'data.message') : null,
            raw: $data,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $txnId): PaymentStatus
    {
        $response = $this->withPassport(fn (string $token): HttpResponse => Http::withToken($token)
            ->acceptJson()
            ->get($this->apiUrl($this->path('status') . $txnId)));

        if (! $response->successful()) {
            throw GatewayException::fromResponse($this->name(), 'Status inquiry failed.', (string) $response->status());
        }

        $data = (array) $response->json();
        $statusValue = (string) (Arr::get($data, 'data.state') ?? Arr::get($data, 'data.status') ?? '');

        return new PaymentStatus(
            state: $this->mapState($statusValue),
            transactionId: $txnId,
            amount: Arr::get($data, 'data.amount') !== null ? (int) Arr::get($data, 'data.amount') : null,
            gatewayCode: $statusValue !== '' ? $statusValue : null,
            message: Arr::get($data, 'data.message') !== null ? (string) Arr::get($data, 'data.message') : null,
            raw: $data,
        );
    }

    /**
     * Compute the expected webhook signature for a raw request body.
     */
    public function computeWebhookSignature(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->requireConfig('webhook_secret'));
    }

    /**
     * Initialise an order and return its tracker/beacon.
     *
     * @throws GatewayException On a failed order init.
     */
    private function initOrder(PaymentRequest $request): string
    {
        $response = $this->withPassport(fn (string $token): HttpResponse => Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->apiUrl($this->path('order')), [
                // VERIFY the exact Order API request body with Safepay docs.
                'amount' => $request->amount,
                'currency' => $request->currency,
                'order_id' => $request->orderId,
            ]));

        if (! $response->successful()) {
            throw GatewayException::fromResponse($this->name(), 'Order initialisation failed.', (string) $response->status());
        }

        $data = (array) $response->json();
        $tracker = Arr::get($data, 'data.tracker') ?? Arr::get($data, 'data.token');

        if (! is_string($tracker) || $tracker === '') {
            throw GatewayException::fromResponse($this->name(), 'Order init response did not contain a tracker.');
        }

        return $tracker;
    }

    /**
     * Run an authenticated call, transparently regenerating the passport token
     * once if the gateway responds 401 (token expired).
     *
     * @param callable(string): HttpResponse $callback
     */
    private function withPassport(callable $callback): HttpResponse
    {
        $response = $callback($this->passportToken());

        if ($response->status() === 401) {
            $response = $callback($this->passportToken(force: true));
        }

        return $response;
    }

    /**
     * Obtain (and cache) the passport token. Valid ~1 hour.
     *
     * @param bool $force Skip the cache and request a fresh token.
     *
     * @throws GatewayException If a token cannot be obtained.
     */
    private function passportToken(bool $force = false): string
    {
        $key = 'pakpay:safepay:passport:' . $this->endpoint('environment');

        if (! $force) {
            $cached = Cache::get($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        // VERIFY the auth scheme/body for the passport endpoint with Safepay.
        $response = Http::withHeaders(['X-SFPY-Merchant-Secret' => $this->requireConfig('secret_key')])
            ->acceptJson()
            ->asJson()
            ->post($this->apiUrl($this->path('passport')), []);

        if (! $response->successful()) {
            throw GatewayException::fromResponse($this->name(), 'Failed to obtain passport token.', (string) $response->status());
        }

        $token = Arr::get((array) $response->json(), 'data.token') ?? Arr::get((array) $response->json(), 'token');

        if (! is_string($token) || $token === '') {
            throw GatewayException::fromResponse($this->name(), 'Passport response did not contain a token.');
        }

        // Cache for 55 minutes (token lives ~60), leaving headroom.
        Cache::put($key, $token, now()->addMinutes(55));

        return $token;
    }

    /**
     * Reject replayed webhooks.
     *
     * Two independent defences, neither of which depends on the timestamp
     * header being covered by the signature:
     *
     *  1. **Single-use signature.** A valid HMAC is unique per event body, so
     *     each one is recorded and any duplicate delivery is rejected. This
     *     blocks a captured-and-resent webhook even when NO timestamp header is
     *     present (that case previously skipped replay protection entirely) and
     *     even when the timestamp is rewritten, since it is not signed.
     *  2. **Freshness window.** When a timestamp IS provided, anything older
     *     than the replay window is rejected before the signature is recorded.
     *
     * Note the trade-off of (1): a genuine gateway *retry* of a delivery your
     * app failed to process is also a duplicate, and will be rejected while the
     * signature is remembered. Handle the webhook idempotently and reconcile
     * against the gateway with status() rather than relying on retries; lower
     * `safepay.webhook_dedup_ttl` if you need the retry window back sooner.
     *
     * @throws SignatureVerificationException
     */
    private function assertNotReplayed(Request $request, string $signature): void
    {
        $ts = $request->header(self::TIMESTAMP_HEADER);

        if ($ts !== null && $ts !== '') {
            $age = abs(time() - (int) $ts);

            if ($age > self::REPLAY_WINDOW) {
                throw SignatureVerificationException::replayDetected($this->name());
            }
        }

        // Cache::add is an atomic check-and-set: it returns false when the key
        // already exists, i.e. this exact signature has been delivered before.
        // Atomicity matters — two concurrent deliveries of the same webhook
        // must not both pass a read-then-write check.
        $key = 'pakpay:safepay:webhook:' . hash('sha256', $signature);

        if (! Cache::add($key, true, now()->addSeconds($this->dedupTtl()))) {
            throw SignatureVerificationException::replayDetected($this->name());
        }
    }

    /**
     * How long (in seconds) a delivered webhook signature is remembered.
     */
    private function dedupTtl(): int
    {
        $ttl = (int) ($this->config['webhook_dedup_ttl'] ?? self::DEDUP_TTL);

        return $ttl > 0 ? $ttl : self::DEDUP_TTL;
    }

    /**
     * Build a full API URL for the current environment.
     */
    private function apiUrl(string $path): string
    {
        return rtrim($this->endpoint('api'), '/') . $path;
    }

    /**
     * Resolve a configured API sub-path.
     */
    private function path(string $key): string
    {
        $path = $this->config['paths'][$key] ?? null;

        if (! is_string($path) || $path === '') {
            throw GatewayException::missingConfig($this->name(), "paths.{$key}");
        }

        return $path;
    }

    /**
     * Map a Safepay state/event string to a normalised PaymentState.
     */
    private function mapState(string $value): string
    {
        // Safepay prefixes its tracker events ("TRACKER_COMPLETED"); strip that
        // and the rest is the vocabulary every driver shares.
        $value = strtolower(trim($value));

        if (str_starts_with($value, 'tracker_')) {
            $value = substr($value, strlen('tracker_'));
        }

        return $this->mapCommonState($value);
    }
}
