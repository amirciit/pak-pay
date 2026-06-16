<?php

declare(strict_types=1);

namespace PakPay\PakPay\Exceptions;

use Exception;

/**
 * Base exception for every error raised by the PakPay package.
 *
 * Catch this type to handle any PakPay failure generically; catch a more
 * specific subclass to react to a particular failure mode.
 */
class PakPayException extends Exception
{
}
