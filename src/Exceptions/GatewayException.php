<?php

declare(strict_types=1);

namespace PakPay\PakPay\Exceptions;

/**
 * Thrown when a gateway rejects a request, returns an error response, or is
 * misconfigured (missing credentials, unreachable endpoint, etc.).
 */
class GatewayException extends PakPayException
{
    /**
     * Create an exception for a missing/empty configuration value.
     *
     * @param string $gateway The gateway key (e.g. "jazzcash").
     * @param string $key     The configuration key that is missing.
     */
    public static function missingConfig(string $gateway, string $key): self
    {
        return new self("Missing required configuration [{$key}] for gateway [{$gateway}]. Set it in config/pakpay.php or the matching .env value.");
    }

    /**
     * Create an exception describing a gateway error response.
     *
     * @param string $gateway The gateway key.
     * @param string $message The human-readable error message.
     * @param string|null $code The gateway-specific response code, if any.
     */
    public static function fromResponse(string $gateway, string $message, ?string $code = null): self
    {
        $suffix = $code !== null ? " (code: {$code})" : '';

        return new self("[{$gateway}] {$message}{$suffix}");
    }
}
