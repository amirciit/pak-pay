<?php

declare(strict_types=1);

namespace PakPay\PakPay\Drivers;

/**
 * Raast (SBP instant payments) gateway driver — SCAFFOLD ONLY.
 *
 * Raast is the State Bank of Pakistan's instant payment rail and is normally
 * reached through an aggregator (e.g. PayFast SBP). This stub exists so it can
 * be implemented later; every flow currently inherits the
 * UnsupportedFlowException behaviour from {@see AbstractGatewayDriver}.
 *
 * TODO (Phase 4):
 *  - Decide aggregator vs direct SBP integration.
 *  - Implement Raast ID / IBAN request-to-pay flow.
 *  - Implement callback signature verification (hash_equals).
 *
 * @see https://www.sbp.org.pk/RAAST  // VERIFY integration path before implementing.
 */
final class RaastDriver extends AbstractGatewayDriver
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'raast';
    }
}
