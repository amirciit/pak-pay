<?php

declare(strict_types=1);

namespace PakPay\PakPay\DTOs;

/**
 * Immutable value object describing the paying customer.
 *
 * Note: no raw card data is ever held here. CNIC, when present, is only the
 * last 6 digits as required by certain wallet APIs (e.g. JazzCash pp_CNIC).
 */
final class Customer
{
    /**
     * @param string|null $name      Customer full name.
     * @param string|null $email     Customer email address.
     * @param string|null $mobile    Mobile number (MSISDN), e.g. "03019680302".
     * @param string|null $cnicLast6 Last 6 digits of the customer CNIC, if required by the gateway.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $cnicLast6 = null,
    ) {
    }

    /**
     * Build a Customer from an associative array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            mobile: isset($data['mobile']) ? (string) $data['mobile'] : null,
            cnicLast6: isset($data['cnic_last6']) ? (string) $data['cnic_last6'] : null,
        );
    }
}
