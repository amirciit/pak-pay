<?php

declare(strict_types=1);

namespace PakPay\PakPay\Exceptions;

/**
 * Thrown when a flow is requested from a gateway that does not support it.
 *
 * The package never fakes an unsupported flow — for example calling charge()
 * (direct wallet) on a hosted-redirect-only gateway raises this exception.
 */
class UnsupportedFlowException extends PakPayException
{
    /**
     * @param string $gateway The gateway key (e.g. "easypaisa").
     * @param string $flow    The flow that is not supported (e.g. "charge").
     */
    public static function make(string $gateway, string $flow): self
    {
        return new self("Gateway [{$gateway}] does not support the [{$flow}] flow.");
    }
}
