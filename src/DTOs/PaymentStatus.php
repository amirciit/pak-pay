<?php

declare(strict_types=1);

namespace PakPay\PakPay\DTOs;

use PakPay\PakPay\Enums\PaymentState;

/**
 * Immutable snapshot of a transaction's current status, returned by status().
 */
final class PaymentStatus
{
    /**
     * @param string               $state        Normalised payment state (a PaymentState::* constant).
     * @param string               $transactionId The gateway transaction id queried.
     * @param int|null             $amount       Amount in paisa, if reported.
     * @param string|null          $gatewayCode  The raw gateway status code.
     * @param string|null          $message      Human-readable gateway message.
     * @param array<string, mixed> $raw          The full raw gateway payload for auditing.
     */
    public function __construct(
        public string $state,
        public string $transactionId,
        public ?int $amount = null,
        public ?string $gatewayCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {
    }

    /**
     * Convenience: is the transaction in the PAID state?
     */
    public function isPaid(): bool
    {
        return PaymentState::isSuccessful($this->state);
    }
}
