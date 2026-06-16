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
}
