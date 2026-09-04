<?php

declare(strict_types=1);

namespace PakPay\PakPay\DTOs;

/**
 * Immutable result of a refund() operation.
 */
final class RefundResult
{
    /**
     * @param bool                 $success      Whether the refund was accepted.
     * @param string               $transactionId The original transaction id that was refunded.
     * @param string|null          $refundId     The gateway refund reference, if issued.
     * @param int|null             $amount       Refunded amount in paisa.
     * @param string|null          $gatewayCode  The raw gateway response code.
     * @param string|null          $message      Human-readable gateway message.
     * @param array<string, mixed> $raw          The full raw gateway payload for auditing.
     */
    public function __construct(
        public bool $success,
        public string $transactionId,
        public ?string $refundId = null,
        public ?int $amount = null,
        public ?string $gatewayCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {
    }

    /**
     * The refund as a plain array (for logging or persisting against the order).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'refund_id' => $this->refundId,
            'amount' => $this->amount,
            'gateway_code' => $this->gatewayCode,
            'message' => $this->message,
            'raw' => $this->raw,
        ];
    }
}
