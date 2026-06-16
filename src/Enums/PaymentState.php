<?php

declare(strict_types=1);

namespace PakPay\PakPay\Enums;

/**
 * The normalised lifecycle state of a payment, mapped from each gateway's
 * own (and wildly different) status codes into one consistent vocabulary.
 *
 * Implemented as a class of string constants (not a native PHP 8.1 enum) so the
 * package keeps working on PHP 8.0. Each state is a lowercase string, so you can
 * compare with `===` (e.g. `$result->state === PaymentState::PAID`) and store the
 * value directly. The `isSuccessful()` / `isFinal()` helpers are static.
 */
final class PaymentState
{
    /** The payment has been created but no money has moved yet. */
    public const PENDING = 'pending';

    /** The customer has been redirected / OTP issued; awaiting completion. */
    public const PROCESSING = 'processing';

    /** Funds were successfully captured. */
    public const PAID = 'paid';

    /** The gateway declined or the customer abandoned the payment. */
    public const FAILED = 'failed';

    /** The payment was cancelled before completion. */
    public const CANCELLED = 'cancelled';

    /** Funds were returned to the customer (full or partial). */
    public const REFUNDED = 'refunded';

    /** The gateway returned a state PakPay could not map. */
    public const UNKNOWN = 'unknown';

    /**
     * This class is a constant holder and is never instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Whether the given state represents a successfully completed payment.
     */
    public static function isSuccessful(string $state): bool
    {
        return $state === self::PAID;
    }

    /**
     * Whether the given state is terminal (no further transitions expected).
     */
    public static function isFinal(string $state): bool
    {
        return in_array($state, [self::PAID, self::FAILED, self::CANCELLED, self::REFUNDED], true);
    }

    /**
     * Every valid state value (handy for validation / tests).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::PAID,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
            self::UNKNOWN,
        ];
    }
}
