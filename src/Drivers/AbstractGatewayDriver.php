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

/**
 * Shared functionality for concrete gateway drivers.
 *
 * Every flow defaults to throwing {@see UnsupportedFlowException}; a concrete
 * driver overrides only the flows it actually supports. This guarantees the
 * package never silently fakes an unsupported flow.
 */
abstract class AbstractGatewayDriver implements GatewayDriver
{
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
}
