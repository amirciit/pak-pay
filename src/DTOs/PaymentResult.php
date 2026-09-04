<?php

declare(strict_types=1);

namespace PakPay\PakPay\DTOs;

use PakPay\PakPay\Enums\PaymentState;

/**
 * Immutable result of a charge() or verifyCallback() operation.
 */
final class PaymentResult
{
    /**
     * @param bool                 $success      Whether the payment succeeded.
     * @param string               $state        Normalised payment state (a PaymentState::* constant).
     * @param string|null          $transactionId The gateway transaction id, if issued.
     * @param string|null          $orderId      The merchant order id this result belongs to.
     * @param int|null             $amount       Captured amount in paisa, if known.
     * @param string|null          $gatewayCode  The raw gateway response code.
     * @param string|null          $message      Human-readable gateway message.
     * @param array<string, mixed> $raw          The full raw gateway payload for auditing.
     */
    public function __construct(
        public bool $success,
        public string $state,
        public ?string $transactionId = null,
        public ?string $orderId = null,
        public ?int $amount = null,
        public ?string $gatewayCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {
    }

    /**
     * Convenience: was the payment captured successfully?
     */
    public function isPaid(): bool
    {
        return $this->success && PaymentState::isSuccessful($this->state);
    }

    /**
     * The result as a plain array — what you persist against the order or write
     * to your payment log after verifying a callback.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'state' => $this->state,
            'transaction_id' => $this->transactionId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'gateway_code' => $this->gatewayCode,
            'message' => $this->message,
            'raw' => $this->raw,
        ];
    }
}
