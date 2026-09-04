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

    /**
     * Whether the transaction has reached a state that will not change again.
     */
    public function isFinal(): bool
    {
        return PaymentState::isFinal($this->state);
    }

    /**
     * The status as a plain array (for logging or reconciliation records).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'gateway_code' => $this->gatewayCode,
            'message' => $this->message,
            'raw' => $this->raw,
        ];
    }
}
