<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\Customer;
use PakPay\PakPay\DTOs\PaymentRequest;
use PakPay\PakPay\DTOs\PaymentResult;
use PakPay\PakPay\DTOs\PaymentStatus;
use PakPay\PakPay\DTOs\RefundResult;
use PakPay\PakPay\Enums\PaymentState;

it('round-trips a payment request through an array', function (): void {
    $request = new PaymentRequest(
        amount: 15000,
        orderId: 'ORDER-1',
        description: 'Two shirts',
        customer: new Customer(name: 'Ali', mobile: '03019680302', cnicLast6: '123456'),
        meta: ['cart' => 42],
    );

    $rebuilt = PaymentRequest::fromArray($request->toArray());

    expect($rebuilt->toArray())->toBe($request->toArray())
        ->and($rebuilt->amount)->toBe(15000)
        ->and($rebuilt->customer->cnicLast6)->toBe('123456');
});

it('serialises a payment result for the payment log', function (): void {
    $result = new PaymentResult(
        success: true,
        state: PaymentState::PAID,
        transactionId: 'T123',
        orderId: 'ORDER-1',
        amount: 15000,
        gatewayCode: '000',
        message: 'Success',
        raw: ['pp_ResponseCode' => '000'],
    );

    expect($result->toArray())->toBe([
        'success' => true,
        'state' => 'paid',
        'transaction_id' => 'T123',
        'order_id' => 'ORDER-1',
        'amount' => 15000,
        'gateway_code' => '000',
        'message' => 'Success',
        'raw' => ['pp_ResponseCode' => '000'],
    ]);
});

it('serialises a status and reports whether it is final', function (): void {
    $paid = new PaymentStatus(state: PaymentState::PAID, transactionId: 'T1', amount: 15000);
    $pending = new PaymentStatus(state: PaymentState::PENDING, transactionId: 'T2');

    expect($paid->isFinal())->toBeTrue()
        ->and($pending->isFinal())->toBeFalse()
        ->and($paid->toArray()['state'])->toBe('paid')
        ->and($paid->toArray()['transaction_id'])->toBe('T1');
});

it('serialises a refund result', function (): void {
    $refund = new RefundResult(success: true, transactionId: 'T1', refundId: 'RF-1', amount: 5000);

    expect($refund->toArray())->toMatchArray([
        'success' => true,
        'transaction_id' => 'T1',
        'refund_id' => 'RF-1',
        'amount' => 5000,
    ]);
});

it('serialises a customer with the keys fromArray reads', function (): void {
    $customer = new Customer(name: 'Ali', email: 'ali@shop.test', mobile: '03019680302', cnicLast6: '123456');

    expect(Customer::fromArray($customer->toArray())->toArray())->toBe($customer->toArray());
});
