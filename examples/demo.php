<?php

declare(strict_types=1);

/**
 * Standalone demo — run:  php examples/demo.php
 *
 * Shows the package's core security working WITHOUT a live gateway:
 *  1. sign a JazzCash payload
 *  2. verify a genuine callback (accepted)
 *  3. verify a tampered callback (rejected)
 *
 * Uses the drivers directly so no full Laravel app is needed.
 */

require __DIR__ . '/../vendor/autoload.php';

use PakPay\PakPay\Drivers\JazzCashDriver;
use PakPay\PakPay\Exceptions\SignatureVerificationException;
use Illuminate\Http\Request;

$driver = new JazzCashDriver([
    'merchant_id' => 'MC1234',
    'password' => 'secret-pass',
    'integrity_salt' => 'abc123salt',
    'return_url' => 'https://shop.test/return',
    'currency' => 'PKR',
    'endpoints' => ['sandbox' => [], 'production' => []],
], 'sandbox');

echo "== 1. Sign an outgoing payload ==\n";
$fields = [
    'pp_Amount' => '10000',          // PKR 100.00 in paisa
    'pp_BillReference' => 'ORDER-1',
    'pp_MerchantID' => 'MC1234',
    'pp_TxnCurrency' => 'PKR',
    'pp_TxnRefNo' => 'T20260101120000ABCDEF',
];
$fields['pp_SecureHash'] = $driver->secureHash($fields);
echo "Secure hash: {$fields['pp_SecureHash']}\n\n";

echo "== 2. Verify a GENUINE callback ==\n";
$genuine = [
    'pp_ResponseCode' => '000',
    'pp_ResponseMessage' => 'Success',
    'pp_TxnRefNo' => 'T20260101120000ABCDEF',
    'pp_BillReference' => 'ORDER-1',
    'pp_Amount' => '10000',
];
$genuine['pp_SecureHash'] = $driver->secureHash($genuine);

$result = $driver->verifyCallback(Request::create('/cb', 'POST', $genuine));
echo "Accepted. paid=" . ($result->isPaid() ? 'yes' : 'no')
    . " txn={$result->transactionId} order={$result->orderId}\n\n";

echo "== 3. Verify a TAMPERED callback (attacker bumps amount) ==\n";
$tampered = $genuine;
$tampered['pp_Amount'] = '999999';   // changed AFTER signing
try {
    $driver->verifyCallback(Request::create('/cb', 'POST', $tampered));
    echo "!! SECURITY HOLE: tampered payload was accepted\n";
} catch (SignatureVerificationException $e) {
    echo "Rejected (as it must be): {$e->getMessage()}\n";
}
