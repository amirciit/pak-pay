<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

use PakPay\PakPay\Contracts\GatewayDriver;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\Exceptions\GatewayException;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Shared functionality for concrete gateway drivers.
 *
 * Every flow defaults to throwing {@see UnsupportedFlowException}; a concrete
 * driver overrides only the flows it actually supports. This guarantees the
 * package never silently fakes an unsupported flow.
 *
 * The protected helpers below carry the plumbing every gateway needs — config
 * lookup, endpoint resolution, JSON decoding, HMAC signing and the one-time
 * hosted-redirect stash — so a concrete driver holds only what is genuinely
 * specific to its gateway.
 */
abstract class AbstractGatewayDriver implements GatewayDriver
{
    /** Cache key prefix for a one-time hosted-redirect payload. */
    protected const REDIRECT_CACHE_PREFIX = 'pakpay:redirect:';

    /** Fallback lifetime (minutes) of a stashed redirect payload. */
    protected const DEFAULT_REDIRECT_TTL = 10;

    /**
     * @param array<string, mixed> $config The resolved config for this gateway.
     * @param string               $mode   "sandbox" or "production".
     */
    public function __construct(
        protected array $config,
        protected string $mode = 'sandbox',
    ) {
    }

    /**
     * The stable key used to identify this gateway (e.g. "jazzcash").
     */
    abstract public function name(): string;

    /**
     * {@inheritDoc}
     */
    public function checkout(PaymentRequest $request): RedirectResponse
    {
        throw UnsupportedFlowException::make($this->name(), 'checkout');
    }

    /**
     * {@inheritDoc}
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        throw UnsupportedFlowException::make($this->name(), 'charge');
    }

    /**
     * {@inheritDoc}
     */
    public function verifyCallback(Request $request): PaymentResult
    {
        throw UnsupportedFlowException::make($this->name(), 'verifyCallback');
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $txnId, int $amount): RefundResult
    {
        throw UnsupportedFlowException::make($this->name(), 'refund');
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $txnId): PaymentStatus
    {
        throw UnsupportedFlowException::make($this->name(), 'status');
    }

    /**
     * Whether the driver is running against production endpoints.
     */
    protected function isProduction(): bool
    {
        return $this->mode === 'production';
    }

    /**
     * Resolve the endpoint URL for the current mode.
     *
     * @param string $key The endpoint key (e.g. "checkout", "wallet").
     *
     * @throws GatewayException If the endpoint is not configured.
     */
    protected function endpoint(string $key): string
    {
        $env = $this->isProduction() ? 'production' : 'sandbox';
        $url = $this->config['endpoints'][$env][$key] ?? null;

        if (! is_string($url) || $url === '') {
            throw GatewayException::missingConfig($this->name(), "endpoints.{$env}.{$key}");
        }

        return $url;
    }

    /**
     * Fetch a required config value or throw if it is missing/empty.
     *
     * @throws GatewayException If the value is missing or empty.
     */
    protected function requireConfig(string $key): string
    {
        $value = $this->config[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw GatewayException::missingConfig($this->name(), $key);
        }

        return $value;
    }

    /**
     * Fetch an optional config value with a fallback default.
     */
    protected function config(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * Stash a signed hosted-checkout payload behind a one-time token and
     * redirect to the package render route, which POSTs it to the gateway.
     *
     * Keeping the payload server-side means no credential or signature ever
     * travels inside the redirect URL, where it would end up in browser
     * history, proxy logs and Referer headers.
     *
     * @param string                $action The hosted-checkout URL to POST to.
     * @param array<string, string> $fields The signed form fields.
     */
    protected function hostedRedirect(string $action, array $fields): RedirectResponse
    {
        $token = (string) Str::uuid();

        Cache::put(self::REDIRECT_CACHE_PREFIX . $token, [
            'action' => $action,
            'fields' => $fields,
            'gateway' => $this->name(),
        ], now()->addMinutes($this->redirectTtl()));

        return new RedirectResponse(route('pakpay.redirect', ['token' => $token]));
    }

    /**
     * How long (in minutes) a stashed redirect payload stays valid.
     */
    protected function redirectTtl(): int
    {
        $ttl = (int) config('pakpay.redirect_ttl', self::DEFAULT_REDIRECT_TTL);

        return $ttl > 0 ? $ttl : self::DEFAULT_REDIRECT_TTL;
    }

    /**
     * Build the canonical string a gateway signature is computed over: every
     * non-empty field value, ordered by field name and joined with "&".
     *
     * @param array<string, mixed>        $fields
     * @param list<string>                $exclude   Field names to drop (e.g. the signature itself).
     * @param callable(string): bool|null $keyFilter Optional predicate a field name must satisfy.
     */
    protected function signableString(array $fields, array $exclude = [], ?callable $keyFilter = null): string
    {
        $signable = [];

        foreach ($fields as $key => $value) {
            $key = (string) $key;

            if (in_array($key, $exclude, true)) {
                continue;
            }

            if ($keyFilter !== null && ! $keyFilter($key)) {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = (string) $value;

            if ($value === '') {
                continue;
            }

            $signable[$key] = $value;
        }

        ksort($signable);

        return implode('&', array_values($signable));
    }

    /**
     * HMAC-SHA256 a message and return upper-case hex, the form in which every
     * gateway in this package exchanges signatures.
     */
    protected function hmac(string $message, string $key): string
    {
        return strtoupper(hash_hmac('sha256', $message, $key));
    }

    /**
     * Decode a JSON gateway response body into an array.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException If the body is not valid JSON.
     */
    protected function decode(string $body): array
    {
        /** @var mixed $data */
        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw GatewayException::fromResponse($this->name(), 'Unexpected non-JSON response from gateway.');
        }

        return $data;
    }
}
