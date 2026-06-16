<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Exceptions\GatewayException;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * NayaPay (Arc) gateway driver — hosted checkout / redirect (embed) only.
 *
 * The direct card API is intentionally NOT implemented because it requires the
 * merchant to be PCI-DSS compliant. Only the non-PCI hosted redirect flow and
 * its signed return callback are supported.
 *
 * Security: the return callback is verified with an HMAC-SHA256 signature over
 * the sorted non-empty fields, compared in constant time (hash_equals).
 *
 * @see https://nayapay.com  // VERIFY Arc endpoints, field names + signing against onboarding docs.
 */
final class NayaPayDriver extends AbstractGatewayDriver
{
    /** Field carrying the signature on the return payload. // VERIFY. */
    private const SIGNATURE_FIELD = 'signature';

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'nayapay';
    }

    /**
     * {@inheritDoc}
     *
     * Builds the signed hosted-checkout fields and redirects through the
     * package render route (self-submitting POST form to NayaPay).
     */
    public function checkout(PaymentRequest $request): RedirectResponse
    {
        $fields = $this->checkoutFields($request);

        $token = (string) Str::uuid();
        Cache::put("pakpay:redirect:{$token}", [
            'action' => $this->endpoint('checkout'),
            'fields' => $fields,
            'gateway' => $this->name(),
        ], now()->addMinutes(10));

        return new RedirectResponse(route('pakpay.redirect', ['token' => $token]));
    }

    /**
     * Build the signed hosted-checkout fields.
     *
     * @return array<string, string>
     */
    public function checkoutFields(PaymentRequest $request): array
    {
        // VERIFY exact field names with the NayaPay Arc integration guide.
        $fields = array_filter([
            'merchant_id' => $this->requireConfig('merchant_id'),
            'order_id' => $request->orderId,
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
            'description' => $request->description ?? ('Order ' . $request->orderId),
            'return_url' => $request->returnUrl ?? $this->requireConfig('return_url'),
        ], static fn ($v): bool => $v !== null && $v !== '');

        $fields[self::SIGNATURE_FIELD] = $this->computeSignature($fields);

        return $fields;
    }

    /**
     * {@inheritDoc}
     *
     * Direct card capture requires PCI compliance and is not supported.
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        throw UnsupportedFlowException::make($this->name(), 'charge');
    }

    /**
     * {@inheritDoc}
     *
     * Verifies the NayaPay return signature with a constant-time comparison.
     */
    public function verifyCallback(Request $request): PaymentResult
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        $provided = isset($payload[self::SIGNATURE_FIELD]) ? (string) $payload[self::SIGNATURE_FIELD] : '';

        if ($provided === '') {
            throw SignatureVerificationException::missing($this->name());
        }

        $fields = $payload;
        unset($fields[self::SIGNATURE_FIELD]);

        $expected = $this->computeSignature($fields);

        if (! hash_equals(strtoupper($expected), strtoupper($provided))) {
            throw SignatureVerificationException::mismatch($this->name());
        }

        $statusValue = (string) ($payload['status'] ?? $payload['transaction_status'] ?? '');
        $state = $this->mapState($statusValue);

        return new PaymentResult(
            success: PaymentState::isSuccessful($state),
            state: $state,
            transactionId: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            orderId: isset($payload['order_id']) ? (string) $payload['order_id'] : null,
            amount: isset($payload['amount']) ? (int) $payload['amount'] : null,
            gatewayCode: $statusValue !== '' ? $statusValue : null,
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            raw: $payload,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $txnId): PaymentStatus
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->requireConfig('api_key')])
            ->acceptJson()
            ->get($this->endpoint('status'), ['order_id' => $txnId]);

        if (! $response->successful()) {
            throw GatewayException::fromResponse($this->name(), 'Status inquiry failed.', (string) $response->status());
        }

        $data = (array) $response->json();
        $statusValue = (string) ($data['status'] ?? '');

        return new PaymentStatus(
            state: $this->mapState($statusValue),
            transactionId: $txnId,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            gatewayCode: $statusValue !== '' ? $statusValue : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
            raw: $data,
        );
    }

    /**
     * Compute the HMAC-SHA256 signature over the sorted non-empty fields.
     *
     * // VERIFY the exact signing scheme with the NayaPay Arc docs.
     *
     * @param array<string, mixed> $fields
     */
    public function computeSignature(array $fields): string
    {
        $key = $this->requireConfig('hash_key');

        $signable = [];
        foreach ($fields as $name => $value) {
            if ($name === self::SIGNATURE_FIELD) {
                continue;
            }
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $signable[$name] = $value;
        }

        ksort($signable);
        $message = implode('&', array_values($signable));

        return strtoupper(hash_hmac('sha256', $message, $key));
    }

    /**
     * Map a NayaPay status string to a normalised PaymentState.
     */
    private function mapState(string $value): string
    {
        return match (strtolower($value)) {
            'paid', 'completed', 'success', 'successful' => PaymentState::PAID,
            'pending', 'in_progress', 'initiated' => PaymentState::PENDING,
            'failed', 'declined' => PaymentState::FAILED,
            'cancelled', 'canceled' => PaymentState::CANCELLED,
            'refunded' => PaymentState::REFUNDED,
            default => PaymentState::UNKNOWN,
        };
    }
}
