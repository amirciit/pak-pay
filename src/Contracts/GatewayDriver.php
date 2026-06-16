<?php

declare(strict_types=1);

namespace PakPay\PakPay\Contracts;

use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\Exceptions\GatewayException;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use PakPay\PakPay\Exceptions\UnsupportedFlowException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/**
 * The single contract every payment gateway driver must implement.
 *
 * Gateways differ in capability. A driver that cannot support a given flow
 * MUST throw {@see UnsupportedFlowException} rather than fake a result.
 */
interface GatewayDriver
{
    /**
     * Start a hosted-redirect checkout.
     *
     * Builds the gateway-specific signed payload and returns a redirect that
     * sends the customer to the gateway's hosted payment page.
     *
     * @param PaymentRequest $request The payment to collect.
     * @return RedirectResponse
     *
     * @throws UnsupportedFlowException If the gateway has no hosted flow.
     * @throws GatewayException         On configuration or gateway errors.
     */
    public function checkout(PaymentRequest $request): RedirectResponse;

    /**
     * Charge directly (e.g. mobile wallet / OTP), without a hosted page.
     *
     * @param PaymentRequest $request The payment to collect.
     * @return PaymentResult
     *
     * @throws UnsupportedFlowException If the gateway has no direct charge flow.
     * @throws GatewayException         On configuration or gateway errors.
     */
    public function charge(PaymentRequest $request): PaymentResult;

    /**
     * Verify and parse a callback/return/webhook request from the gateway.
     *
     * Implementations MUST verify the signature using a constant-time
     * comparison (hash_equals) before trusting any field, and apply replay
     * protection where the gateway provides a timestamp/nonce.
     *
     * @param Request $request The inbound HTTP request from the gateway.
     * @return PaymentResult
     *
     * @throws SignatureVerificationException If the signature is missing, invalid, or replayed.
     * @throws GatewayException               On malformed payloads.
     */
    public function verifyCallback(Request $request): PaymentResult;

    /**
     * Refund a previously captured transaction (full or partial).
     *
     * @param string $txnId  The gateway transaction id to refund.
     * @param int    $amount Amount to refund in paisa.
     * @return RefundResult
     *
     * @throws UnsupportedFlowException If the gateway has no refund API.
     * @throws GatewayException         On configuration or gateway errors.
     */
    public function refund(string $txnId, int $amount): RefundResult;

    /**
     * Query the current status of a transaction.
     *
     * @param string $txnId The gateway transaction id to query.
     * @return PaymentStatus
     *
     * @throws UnsupportedFlowException If the gateway has no status API.
     * @throws GatewayException         On configuration or gateway errors.
     */
    public function status(string $txnId): PaymentStatus;
}
