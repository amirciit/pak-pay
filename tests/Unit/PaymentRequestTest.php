<?php

declare(strict_types=1);

use PakPay\PakPay\DTOs\Customer;
use PakPay\PakPay\DTOs\PaymentRequest;

it('rejects a non-positive amount', function (int $amount): void {
    new PaymentRequest(amount: $amount, orderId: 'ORDER-1');
})->with([0, -1, -15000])->throws(InvalidArgumentException::class);

it('rejects a blank order id', function (string $orderId): void {
    new PaymentRequest(amount: 10000, orderId: $orderId);
})->with(['', '   '])->throws(InvalidArgumentException::class);

it('formats paisa as a major-unit decimal string', function (int $paisa, string $expected): void {
    expect((new PaymentRequest(amount: $paisa, orderId: 'ORDER-1'))->amountAsDecimal())->toBe($expected);
})->with([
    [1, '0.01'],
    [50, '0.50'],
    [15000, '150.00'],
    [123456, '1234.56'],
    [100000000, '1000000.00'],
]);

it('builds from an array, including the nested customer', function (): void {
    $request = PaymentRequest::fromArray([
        'amount' => 15000,
        'order_id' => 'ORDER-9',
        'currency' => 'PKR',
        'description' => 'Two shirts',
        'return_url' => 'https://shop.test/return',
        'customer' => [
            'name' => 'Ali',
            'email' => 'ali@shop.test',
            'mobile' => '03019680302',
            'cnic_last6' => '123456',
        ],
        'meta' => ['cart' => 42],
    ]);

    expect($request->amount)->toBe(15000)
        ->and($request->orderId)->toBe('ORDER-9')
        ->and($request->returnUrl)->toBe('https://shop.test/return')
        ->and($request->meta)->toBe(['cart' => 42])
        ->and($request->customer)->toBeInstanceOf(Customer::class)
        ->and($request->customer->mobile)->toBe('03019680302')
        ->and($request->customer->cnicLast6)->toBe('123456');
});

it('defaults to PKR with no customer and no meta', function (): void {
    $request = PaymentRequest::fromArray(['amount' => 100, 'order_id' => 'ORDER-1']);

    expect($request->currency)->toBe('PKR')
        ->and($request->customer)->toBeNull()
        ->and($request->meta)->toBe([]);
});
