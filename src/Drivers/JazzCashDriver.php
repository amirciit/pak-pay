<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\Enums\PaymentState;
use PakPay\PakPay\Exceptions\GatewayException;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * JazzCash (PSO) gateway driver.
 *
 * Supports:
 *  - Hosted Checkout (form-POST redirect, return hash verified)
 *  - Mobile Wallet REST API v2.0 (DoMWalletTransaction) direct charge
 *  - Transaction status inquiry
 *  - Refund
 *
 * Security: every payload is signed with an HMAC-SHA256 "secure hash" built
 * from the Integrity Salt and the alphabetically-sorted pp_* field values.
 * Incoming returns are verified with the same algorithm and a constant-time
 * comparison (hash_equals).
 *
 * @see https://sandbox.jazzcash.com.pk  // VERIFY field names + endpoints against the official Integration Guide.
 */
final class JazzCashDriver extends AbstractGatewayDriver
{
    /** JazzCash API version used in pp_Version. // VERIFY current version. */
    private const VERSION = '2.0';

    /** Success response code returned by JazzCash. */
    private const SUCCESS_CODE = '000';

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'jazzcash';
    }

    /**
     * {@inheritDoc}
     *
     * JazzCash hosted checkout is a browser form POST, which a 302 redirect
     * cannot perform directly. We therefore stash the signed payload behind a
     * one-time token and redirect to the package's internal render route, which
     * emits a self-submitting POST form to the JazzCash hosted page.
     *
     * If you would rather render the form yourself, call {@see checkoutForm()}.
     */
    public function checkout(PaymentRequest $request): RedirectResponse
    {
        $fields = $this->hostedFields($request);

        $token = (string) Str::uuid();

        // Short-lived stash so credentials are not exposed in a redirect URL.
        Cache::put("pakpay:redirect:{$token}", [
            'action' => $this->endpoint('checkout'),
            'fields' => $fields,
            'gateway' => $this->name(),
        ], now()->addMinutes(10));

        return new RedirectResponse(route('pakpay.redirect', ['token' => $token]));
    }

    /**
     * Render the auto-submitting hosted-checkout form HTML.
     *
     * Use this in a controller when you need to POST (not 302-redirect) the
     * customer to JazzCash, e.g. `return response($driver->checkoutForm($req));`.
     *
     * @return string Fully-formed HTML document that POSTs to JazzCash on load.
     */
    public function checkoutForm(PaymentRequest $request): string
    {
        return $this->autoSubmitForm($this->endpoint('checkout'), $this->hostedFields($request));
    }

    /**
     * Build the signed hosted-checkout fields, honouring the
     * `hosted_send_password` security toggle (see config/pakpay.php).
     *
     * @return array<string, string>
     */
    private function hostedFields(PaymentRequest $request): array
    {
        $fields = $this->baseFields($request, txnType: 'MPAY');

        // Optionally keep the merchant password out of the browser-rendered form.
        if (! ($this->config['hosted_send_password'] ?? true)) {
            unset($fields['pp_Password']);
        }

        $fields['pp_SecureHash'] = $this->secureHash($fields);

        return $fields;
    }

    /**
     * {@inheritDoc}
     *
     * Direct mobile-wallet charge via DoMWalletTransaction. Requires the
     * customer's mobile number and the last 6 digits of their CNIC.
     */
    public function charge(PaymentRequest $request): PaymentResult
    {
        $customer = $request->customer;

        if ($customer === null || $customer->mobile === null || $customer->mobile === '') {
            throw GatewayException::fromResponse($this->name(), 'A customer mobile number is required for a wallet charge.');
        }

        if ($customer->cnicLast6 === null || $customer->cnicLast6 === '') {
            throw GatewayException::fromResponse($this->name(), 'pp_CNIC (last 6 digits of CNIC) is required for a wallet charge.');
        }

        $fields = $this->baseFields($request, txnType: 'MWALLET');
        $fields['pp_MobileNumber'] = $customer->mobile;
        $fields['pp_CNIC'] = $customer->cnicLast6;
        $fields['pp_SecureHash'] = $this->secureHash($fields);

        $response = Http::asJson()
            ->acceptJson()
            ->post($this->endpoint('wallet'), $fields);

        $data = $this->decode($response->body());

        $code = (string) ($data['pp_ResponseCode'] ?? '');
        $success = $code === self::SUCCESS_CODE;

        return new PaymentResult(
            success: $success,
            state: $success ? PaymentState::PAID : PaymentState::FAILED,
            transactionId: isset($data['pp_TxnRefNo']) ? (string) $data['pp_TxnRefNo'] : null,
            orderId: $request->orderId,
            amount: isset($data['pp_Amount']) ? (int) $data['pp_Amount'] : $request->amount,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($data['pp_ResponseMessage']) ? (string) $data['pp_ResponseMessage'] : null,
            raw: $data,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Verifies the secure hash on a JazzCash return/IPN payload using a
     * constant-time comparison before trusting any field.
     */
    public function verifyCallback(Request $request): PaymentResult
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        $provided = isset($payload['pp_SecureHash']) ? (string) $payload['pp_SecureHash'] : '';

        if ($provided === '') {
            throw SignatureVerificationException::missing($this->name());
        }

        // Recompute over every field EXCEPT the hash itself.
        $fields = $payload;
        unset($fields['pp_SecureHash']);

        $expected = $this->secureHash($fields);

        // Constant-time comparison defeats timing attacks. JazzCash hashes are
        // upper-case hex; normalise both sides before comparing.
        if (! hash_equals(strtoupper($expected), strtoupper($provided))) {
            throw SignatureVerificationException::mismatch($this->name());
        }

        $code = (string) ($payload['pp_ResponseCode'] ?? '');
        $success = $code === self::SUCCESS_CODE;

        return new PaymentResult(
            success: $success,
            state: $success ? PaymentState::PAID : PaymentState::FAILED,
            transactionId: isset($payload['pp_TxnRefNo']) ? (string) $payload['pp_TxnRefNo'] : null,
            orderId: isset($payload['pp_BillReference']) ? (string) $payload['pp_BillReference'] : null,
            amount: isset($payload['pp_Amount']) ? (int) $payload['pp_Amount'] : null,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($payload['pp_ResponseMessage']) ? (string) $payload['pp_ResponseMessage'] : null,
            raw: $payload,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $txnId, int $amount): RefundResult
    {
        $fields = [
            'pp_Version' => self::VERSION,
            'pp_TxnType' => 'REFUND',
            'pp_MerchantID' => $this->requireConfig('merchant_id'),
            'pp_Password' => $this->requireConfig('password'),
            'pp_TxnRefNo' => $txnId,
            'pp_Amount' => (string) $amount,
            'pp_TxnCurrency' => $this->config('currency', 'PKR') ?? 'PKR',
        ];
        $fields['pp_SecureHash'] = $this->secureHash($fields);

        $response = Http::asJson()->acceptJson()->post($this->endpoint('refund'), $fields);
        $data = $this->decode($response->body());

        $code = (string) ($data['pp_ResponseCode'] ?? '');
        $success = $code === self::SUCCESS_CODE;

        return new RefundResult(
            success: $success,
            transactionId: $txnId,
            refundId: isset($data['pp_RetreivalReferenceNo']) ? (string) $data['pp_RetreivalReferenceNo'] : null,
            amount: $amount,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($data['pp_ResponseMessage']) ? (string) $data['pp_ResponseMessage'] : null,
            raw: $data,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $txnId): PaymentStatus
    {
        $fields = [
            'pp_Version' => self::VERSION,
            'pp_TxnType' => 'INQUIRY',
            'pp_MerchantID' => $this->requireConfig('merchant_id'),
            'pp_Password' => $this->requireConfig('password'),
            'pp_TxnRefNo' => $txnId,
        ];
        $fields['pp_SecureHash'] = $this->secureHash($fields);

        $response = Http::asJson()->acceptJson()->post($this->endpoint('status'), $fields);
        $data = $this->decode($response->body());

        $code = (string) ($data['pp_ResponseCode'] ?? '');

        return new PaymentStatus(
            state: $this->mapState($code, (string) ($data['pp_Status'] ?? '')),
            transactionId: $txnId,
            amount: isset($data['pp_Amount']) ? (int) $data['pp_Amount'] : null,
            gatewayCode: $code !== '' ? $code : null,
            message: isset($data['pp_ResponseMessage']) ? (string) $data['pp_ResponseMessage'] : null,
            raw: $data,
        );
    }

    /**
     * Build the common pp_* fields shared by checkout/charge requests.
     *
     * @param string $txnType The pp_TxnType (e.g. "MPAY", "MWALLET").
     * @return array<string, string>
     */
    private function baseFields(PaymentRequest $request, string $txnType): array
    {
        $now = new DateTimeImmutable();
        // Expiry one hour out. // VERIFY allowed window with JazzCash docs.
        $expiry = $now->modify('+1 hour');

        return array_filter([
            'pp_Version' => self::VERSION,
            'pp_TxnType' => $txnType,
            'pp_Language' => $this->config('language', 'EN') ?? 'EN',
            'pp_MerchantID' => $this->requireConfig('merchant_id'),
            // SECURITY: in the hosted (MPAY) flow this value is rendered into a
            // browser form and is therefore visible in the page source. JazzCash
            // historically requires pp_Password in the hosted form; // VERIFY
            // whether the current Hosted Checkout still needs it and, if not,
            // drop it from checkout() so the merchant password is never exposed.
            'pp_Password' => $this->requireConfig('password'),
            'pp_TxnRefNo' => 'T' . $now->format('YmdHis') . Str::upper(Str::random(6)),
            'pp_Amount' => (string) $request->amount, // paisa, no decimals
            'pp_TxnCurrency' => $request->currency,
            'pp_TxnDateTime' => $now->format('YmdHis'),
            'pp_TxnExpiryDateTime' => $expiry->format('YmdHis'),
            'pp_BillReference' => $request->orderId,
            'pp_Description' => $request->description ?? ('Order ' . $request->orderId),
            'pp_ReturnURL' => $request->returnUrl ?? $this->requireConfig('return_url'),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Compute the JazzCash HMAC-SHA256 secure hash.
     *
     * Algorithm (// VERIFY against the official Integration Guide):
     *  1. Take all non-empty fields whose key starts with "pp_" or "ppmpf_",
     *     excluding pp_SecureHash itself.
     *  2. Sort them by key (ascending).
     *  3. Join the VALUES with "&", prefixed by the Integrity Salt and "&".
     *  4. HMAC-SHA256 the result, keyed by the Integrity Salt; return upper-hex.
     *
     * @param array<string, mixed> $fields
     */
    public function secureHash(array $fields): string
    {
        $salt = $this->requireConfig('integrity_salt');

        $signable = [];
        foreach ($fields as $key => $value) {
            if ($key === 'pp_SecureHash') {
                continue;
            }
            if (! str_starts_with($key, 'pp_') && ! str_starts_with($key, 'ppmpf_')) {
                continue;
            }
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $signable[$key] = $value;
        }

        ksort($signable);

        $message = $salt . '&' . implode('&', array_values($signable));

        return strtoupper(hash_hmac('sha256', $message, $salt));
    }

    /**
     * Map a JazzCash response/status code to a normalised PaymentState.
     */
    private function mapState(string $code, string $status): string
    {
        if ($code === self::SUCCESS_CODE) {
            return PaymentState::PAID;
        }

        return match (strtolower($status)) {
            'pending', 'in progress' => PaymentState::PENDING,
            'completed', 'paid', 'success' => PaymentState::PAID,
            'failed', 'declined' => PaymentState::FAILED,
            'cancelled' => PaymentState::CANCELLED,
            default => $code === '' ? PaymentState::UNKNOWN : PaymentState::FAILED,
        };
    }

    /**
     * Decode a JSON gateway response body into an array.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayException If the body is not valid JSON.
     */
    private function decode(string $body): array
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw GatewayException::fromResponse($this->name(), 'Unexpected non-JSON response from gateway.');
        }

        return $data;
    }

    /**
     * Build a self-submitting HTML form for the hosted-checkout POST.
     *
     * @param array<string, string> $fields
     */
    private function autoSubmitForm(string $action, array $fields): string
    {
        $inputs = '';
        foreach ($fields as $name => $value) {
            $inputs .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                htmlspecialchars($name, ENT_QUOTES),
                htmlspecialchars($value, ENT_QUOTES)
            );
        }

        return sprintf(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Redirecting…</title></head>'
            . '<body onload="document.forms[0].submit()">'
            . '<form method="POST" action="%s">%s<noscript><button type="submit">Continue to JazzCash</button></noscript></form>'
            . '</body></html>',
            htmlspecialchars($action, ENT_QUOTES),
            $inputs
        );
    }
}
