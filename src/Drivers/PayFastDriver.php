<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

/**
 * PayFast (Pakistan) gateway driver — SCAFFOLD ONLY.
 *
 * PayFast supports hosted checkout and Raast / SBP instant payments. This stub
 * exists so the gateway can be wired in later; every flow currently inherits
 * the UnsupportedFlowException behaviour from {@see AbstractGatewayDriver}.
 *
 * TODO (Phase 4):
 *  - Implement ACCESS token + hosted checkout (GETTOKEN / form POST).
 *  - Implement Raast / SBP instant via PayFast.
 *  - Implement return + IPN signature verification (hash_equals).
 *  - Implement status inquiry and refund.
 *
 * @see https://gopayfast.com  // VERIFY all endpoints + signing against official docs.
 */
final class PayFastDriver extends AbstractGatewayDriver
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'payfast';
    }
}
