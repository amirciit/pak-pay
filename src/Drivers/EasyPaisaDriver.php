<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Exceptions\GatewayException;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * EasyPaisa (Telenor Microfinance Bank) gateway driver.
 *
 * Supports:
 *  - Hosted Checkout (form-POST redirect; request signed with merchantHashedReq)
 *  - Mobile Account (OTP) direct charge via the REST API
 *  - Transaction status inquiry
 *
 * Security: the hosted request is signed with an AES-128-ECB "merchantHashedReq"
 * built from the hash key. Incoming returns are verified with an HMAC-SHA256
 * signature using a constant-time comparison (hash_equals).
 *
 * @see https://easypay.easypaisa.com.pk  // VERIFY field names, hashing + endpoints against the official Merchant Integration Guide.
 */
final class EasyPaisaDriver extends AbstractGatewayDriver
{
    /** EasyPaisa response code denoting success. // VERIFY. */
    private const SUCCESS_CODE = '0000';

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'easypaisa';
    }

    /**
     * {@inheritDoc}
     *
     * Builds the signed hosted-checkout parameters and redirects through the
     * package render route, which POSTs a self-submitting form to EasyPaisa.
     */
    public function checkout(PaymentRequest $request): RedirectResponse
    {
        return $this->hostedRedirect($this->endpoint('checkout'), $this->checkoutFields($request));
    }

    /**
     * Build the signed hosted-checkout fields, including merchantHashedReq.
     *
     * @return array<string, string>
     */
    public function checkoutFields(PaymentRequest $request): array
    {
        // VERIFY exact field names + order with the EasyPaisa integration guide.
        $params = array_filter([
            'amount' => $request->amountAsDecimal(),
            'orderRefNum' => $request->orderId,
            'paymentMethod' => 'InitialRequest',
            'postBackURL' => $request->returnUrl ?? $this->requireConfig('return_url'),
            'storeId' => $this->requireConfig('store_id'),
            'expiryDate' => (new DateTimeImmutable('+1 day'))->format('Ymd His'),
        ], static fn ($value): bool => $value !== null && $value !== '');

        $fields = $params;
        $fields['merchantHashedReq'] = $this->merchantHashedReq($params);
        $fields['autoRedirect'] = '1';

        return $fields;
    }

    /**
     * {@inheritDoc}
     *
     * Mobile Account (OTP) charge. The customer receives an OTP on their
     * EasyPaisa account; confirmation of that OTP happens on EasyPaisa's side,
     * so this initiates the transaction and returns its pending/paid result.
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        $customer = $request->customer;

        if ($customer === null || $customer->mobile === null || $customer->mobile === '') {
            throw GatewayException::fromResponse($this->name(), 'A customer mobile number (MSISDN) is required for a Mobile Account charge.');
        }

        // VERIFY request body + auth scheme with the EasyPaisa REST guide.
        $payload = [
            'orderId' => $request->orderId,
            'storeId' => $this->requireConfig('store_id'),
            'transactionAmount' => $request->amountAsDecimal(),
            'transactionType' => 'MA',
            'mobileAccountNo' => $customer->mobile,
            'emailAddress' => $customer->email,
        ];

        $response = Http::withHeaders([
            // EasyPaisa REST expects Basic credentials. // VERIFY header name/format.
            'Credentials' => base64_encode($this->requireConfig('account_num') . ':' . $this->requireConfig('hash_key')),
        ])->acceptJson()->asJson()->post($this->endpoint('mobile_account'), array_filter($payload, static fn ($v) => $v !== null));

        $data = $this->decodeResponse($response);

        $code = (string) ($data['responseCode'] ?? '');
        $success = $code === self::SUCCESS_CODE;

        return new PaymentResult(
            success: $success,
            state: $success ? PaymentState::PAID : PaymentState::PENDING,
            transactionId: isset($data['transactionId']) ? (string) $data['transactionId'] : null,
            orderId: $request->orderId,
            amount: $request->amount,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($data['responseDesc']) ? (string) $data['responseDesc'] : null,
            raw: $data,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Verifies the EasyPaisa return signature with a constant-time comparison.
     *
     * NOTE: EasyPaisa's exact return-signature scheme varies by integration.
     * This implementation expects an HMAC-SHA256 hex signature in a `signature`
     * field, computed over the sorted non-empty response values keyed by the
     * hash key. // VERIFY against your account's actual return payload and
     * adjust {@see expectedReturnSignature()} accordingly.
     */
    public function verifyCallback(Request $request): PaymentResult
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        $provided = isset($payload['signature']) ? (string) $payload['signature'] : '';

        if ($provided === '') {
            throw SignatureVerificationException::missing($this->name());
        }

        $fields = $payload;
        unset($fields['signature']);

        $expected = $this->expectedReturnSignature($fields);

        if (! hash_equals(strtoupper($expected), strtoupper($provided))) {
            throw SignatureVerificationException::mismatch($this->name());
        }

        $code = (string) ($payload['status'] ?? $payload['responseCode'] ?? '');
        $success = $code === self::SUCCESS_CODE;

        return new PaymentResult(
            success: $success,
            state: $success ? PaymentState::PAID : PaymentState::FAILED,
            transactionId: isset($payload['transactionId']) ? (string) $payload['transactionId'] : null,
            orderId: isset($payload['orderRefNum']) ? (string) $payload['orderRefNum'] : null,
            amount: isset($payload['amount']) ? (int) round(((float) $payload['amount']) * 100) : null,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($payload['desc']) ? (string) $payload['desc'] : null,
            raw: $payload,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $txnId): PaymentStatus
    {
        // VERIFY request body + auth with the EasyPaisa REST inquiry guide.
        $payload = [
            'orderId' => $txnId,
            'storeId' => $this->requireConfig('store_id'),
        ];

        $response = Http::withHeaders([
            'Credentials' => base64_encode($this->requireConfig('account_num') . ':' . $this->requireConfig('hash_key')),
        ])->acceptJson()->asJson()->post($this->endpoint('status'), $payload);

        $data = $this->decodeResponse($response);
        $code = (string) ($data['responseCode'] ?? '');

        return new PaymentStatus(
            state: $code === self::SUCCESS_CODE ? PaymentState::PAID : PaymentState::UNKNOWN,
            transactionId: $txnId,
            amount: isset($data['transactionAmount']) ? (int) round(((float) $data['transactionAmount']) * 100) : null,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($data['responseDesc']) ? (string) $data['responseDesc'] : null,
            raw: $data,
        );
    }

    /**
     * Compute the AES-128-ECB merchantHashedReq for a hosted request.
     *
     * Algorithm (// VERIFY against the official guide):
     *  1. Sort params by key; join as "key=value&key=value".
     *  2. AES-128-ECB encrypt with the 16-byte hash key (PKCS7 padding).
     *  3. Base64-encode the ciphertext.
     *
     * @param array<string, string> $params
     *
     * @throws GatewayException If encryption fails or the key is invalid.
     */
    public function merchantHashedReq(array $params): string
    {
        $key = $this->requireConfig('hash_key');

        ksort($params);
        $plain = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $cipher = openssl_encrypt($plain, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);

        if ($cipher === false) {
            throw GatewayException::fromResponse($this->name(), 'Failed to compute merchantHashedReq (check that hash_key is a valid 16-byte AES key).');
        }

        return base64_encode($cipher);
    }

    /**
     * Compute the expected HMAC-SHA256 signature for a return payload.
     *
     * The signed message is built from "key=value" pairs rather than values
     * alone, so field boundaries are unambiguous: values-only concatenation
     * lets {a:"x", b:"y&z"} and {a:"x&y", b:"z"} hash identically, which an
     * attacker can use to shift a value across fields without changing the
     * signature.
     *
     * // VERIFY: replace with EasyPaisa's documented return-signature scheme and
     * align this construction with what your account's callback actually sends.
     *
     * @param array<string, mixed> $fields The response fields, excluding `signature`.
     */
    public function expectedReturnSignature(array $fields): string
    {
        return $this->hmac(
            $this->signablePairs($fields, ['signature']),
            $this->requireConfig('hash_key')
        );
    }
}
