<?php

declare(strict_types=1);

namespace PakPay\PakPay\DTOs;

use InvalidArgumentException;

/**
 * Immutable value object describing a payment the merchant wants to collect.
 *
 * Amounts are always expressed in the smallest currency unit (paisa for PKR)
 * to avoid floating-point rounding errors. 1 PKR === 100.
 */
final class PaymentRequest
{
    /**
     * @param int                  $amount      Amount in the smallest currency unit (paisa). Must be > 0.
     * @param string               $orderId     Unique merchant order/reference id.
     * @param string               $currency    ISO currency code. Pakistani gateways use "PKR".
     * @param string|null          $description Human-readable description shown to the customer.
     * @param Customer|null        $customer    The paying customer.
     * @param string|null          $returnUrl   Override for the gateway's configured return URL.
     * @param array<string, mixed> $meta        Arbitrary merchant metadata echoed back where supported.
     */
    public function __construct(
        public int $amount,
        public string $orderId,
        public string $currency = 'PKR',
        public ?string $description = null,
        public ?Customer $customer = null,
        public ?string $returnUrl = null,
        public array $meta = [],
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('PaymentRequest amount must be a positive integer (in paisa).');
        }

        if (trim($orderId) === '') {
            throw new InvalidArgumentException('PaymentRequest orderId must not be empty.');
        }

        // Surrounding whitespace in an order id is invisible in a dashboard but
        // very visible in a signature, so normalise it before it is signed.
        $this->orderId = trim($orderId);

        $this->currency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException(
                "PaymentRequest currency must be a 3-letter ISO 4217 code, got [{$currency}]."
            );
        }
    }

    /**
     * The request as a plain array (for logging or persisting alongside an order).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'order_id' => $this->orderId,
            'currency' => $this->currency,
            'description' => $this->description,
            'customer' => $this->customer?->toArray(),
            'return_url' => $this->returnUrl,
            'meta' => $this->meta,
        ];
    }

    /**
     * The amount as a decimal string (e.g. 15000 paisa -> "150.00").
     *
     * Useful for gateways that expect a major-unit decimal amount.
     */
    public function amountAsDecimal(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }

    /**
     * Build a PaymentRequest from an associative array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (int) ($data['amount'] ?? 0),
            orderId: (string) ($data['order_id'] ?? ''),
            currency: (string) ($data['currency'] ?? 'PKR'),
            description: isset($data['description']) ? (string) $data['description'] : null,
            customer: isset($data['customer']) && is_array($data['customer'])
                ? Customer::fromArray($data['customer'])
                : null,
            returnUrl: isset($data['return_url']) ? (string) $data['return_url'] : null,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }
}
