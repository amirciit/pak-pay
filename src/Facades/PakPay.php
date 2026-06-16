<?php

declare(strict_types=1);

namespace PakPay\PakPay\Facades;

use PakPay\PakPay\Contracts\GatewayDriver;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\PakPayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static GatewayDriver gateway(string|null $name = null)
 * @method static RedirectResponse checkout(PaymentRequest $request)
 * @method static PaymentResult charge(PaymentRequest $request)
 * @method static PaymentResult verifyCallback(Request $request)
 * @method static RefundResult refund(string $txnId, int $amount)
 * @method static PaymentStatus status(string $txnId)
 *
 * @see PakPayManager
 */
final class PakPay extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PakPayManager::class;
    }
}
