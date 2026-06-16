<?php

declare(strict_types=1);

namespace PakPay\PakPay;

use PakPay\PakPay\Contracts\GatewayDriver;
use PakPay\PakPay\Drivers\EasyPaisaDriver;
use PakPay\PakPay\Drivers\JazzCashDriver;
use PakPay\PakPay\Drivers\NayaPayDriver;
use PakPay\PakPay\Drivers\PayFastDriver;
use PakPay\PakPay\Drivers\RaastDriver;
use PakPay\PakPay\Drivers\SafepayDriver;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\Exceptions\GatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Manager;

/**
 * Factory + facade root for PakPay.
 *
 * Resolves and caches gateway drivers via Laravel's Manager pattern, and
 * proxies the contract methods to the default gateway so callers can write
 * either `PakPay::gateway('jazzcash')->checkout($r)` or just
 * `PakPay::checkout($r)` (using the configured default).
 *
 * @mixin GatewayDriver
 */
class PakPayManager extends Manager
{
    /**
     * The default gateway key, from config('pakpay.default').
     */
    public function getDefaultDriver(): string
    {
        /** @var string $default */
        $default = $this->config->get('pakpay.default', 'jazzcash');

        return $default;
    }

    /**
     * Resolve a gateway driver by key (alias of driver()).
     *
     * @param string|null $name e.g. "jazzcash", "easypaisa". Null = default.
     */
    public function gateway(?string $name = null): GatewayDriver
    {
        /** @var GatewayDriver $driver */
        $driver = $this->driver($name);

        return $driver;
    }

    /**
     * Build the JazzCash driver instance.
     */
    protected function createJazzcashDriver(): GatewayDriver
    {
        return new JazzCashDriver($this->gatewayConfig('jazzcash'), $this->mode());
    }

    /**
     * Build the EasyPaisa driver instance.
     */
    protected function createEasypaisaDriver(): GatewayDriver
    {
        return new EasyPaisaDriver($this->gatewayConfig('easypaisa'), $this->mode());
    }

    /**
     * Build the Safepay driver instance.
     */
    protected function createSafepayDriver(): GatewayDriver
    {
        return new SafepayDriver($this->gatewayConfig('safepay'), $this->mode());
    }

    /**
     * Build the NayaPay driver instance.
     */
    protected function createNayapayDriver(): GatewayDriver
    {
        return new NayaPayDriver($this->gatewayConfig('nayapay'), $this->mode());
    }

    /**
     * Build the PayFast driver instance (scaffold).
     */
    protected function createPayfastDriver(): GatewayDriver
    {
        return new PayFastDriver($this->gatewayConfig('payfast'), $this->mode());
    }

    /**
     * Build the Raast driver instance (scaffold).
     */
    protected function createRaastDriver(): GatewayDriver
    {
        return new RaastDriver($this->gatewayConfig('raast'), $this->mode());
    }

    /**
     * Resolve the per-gateway config array.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException If the gateway is not configured.
     */
    protected function gatewayConfig(string $gateway): array
    {
        /** @var array<string, mixed>|null $config */
        $config = $this->config->get("pakpay.gateways.{$gateway}");

        if (! is_array($config)) {
            throw GatewayException::fromResponse($gateway, "No configuration found for gateway [{$gateway}] in config/pakpay.php.");
        }

        return $config;
    }

    /**
     * The global sandbox/production mode.
     */
    protected function mode(): string
    {
        /** @var string $mode */
        $mode = $this->config->get('pakpay.mode', 'sandbox');

        return $mode === 'production' ? 'production' : 'sandbox';
    }

    /**
     * Proxy: start a hosted checkout on the default gateway.
     */
    public function checkout(PaymentRequest $request): RedirectResponse
    {
        return $this->gateway()->checkout($request);
    }

    /**
     * Proxy: direct charge on the default gateway.
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        return $this->gateway()->charge($request);
    }

    /**
     * Proxy: verify a callback on the default gateway.
     */
    public function verifyCallback(Request $request): PaymentResult
    {
        return $this->gateway()->verifyCallback($request);
    }

    /**
     * Proxy: refund on the default gateway.
     */
    public function refund(string $txnId, int $amount): RefundResult
    {
        return $this->gateway()->refund($txnId, $amount);
    }

    /**
     * Proxy: status inquiry on the default gateway.
     */
    public function status(string $txnId): PaymentStatus
    {
        return $this->gateway()->status($txnId);
    }
}
