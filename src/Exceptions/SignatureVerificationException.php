<?php

declare(strict_types=1);

namespace PakPay\PakPay\Exceptions;

/**
 * Thrown when an incoming callback/webhook/return payload fails signature
 * verification, fails replay protection, or carries a malformed signature.
 *
 * A thrown instance always means the payload MUST NOT be trusted.
 */
class SignatureVerificationException extends PakPayException
{
    /**
     * The computed/expected signature did not match the one supplied.
     *
     * @param string $gateway The gateway key (e.g. "jazzcash").
     */
    public static function mismatch(string $gateway): self
    {
        return new self("[{$gateway}] Signature verification failed: the secure hash does not match the expected value. The payload has been tampered with or the integrity secret is wrong.");
    }

    /**
     * The payload did not contain a signature to verify.
     *
     * @param string $gateway The gateway key.
     */
    public static function missing(string $gateway): self
    {
        return new self("[{$gateway}] Signature verification failed: no signature was present in the payload.");
    }

    /**
     * The payload is older than the allowed window (possible replay attack).
     *
     * @param string $gateway The gateway key.
     */
    public static function replayDetected(string $gateway): self
    {
        return new self("[{$gateway}] Replay protection triggered: the payload timestamp/nonce is outside the accepted window.");
    }
}
