<?php

declare(strict_types=1);

use PakPay\PakPay\Support\AutoSubmitForm;

it('renders a form that posts every field to the gateway', function (): void {
    $html = AutoSubmitForm::render('https://gateway.test/pay', [
        'pp_Amount' => '10000',
        'pp_SecureHash' => 'ABCDEF',
    ]);

    expect($html)->toContain('action="https://gateway.test/pay"')
        ->and($html)->toContain('method="POST"')
        ->and($html)->toContain('name="pp_Amount" value="10000"')
        ->and($html)->toContain('name="pp_SecureHash" value="ABCDEF"')
        ->and($html)->toContain('document.forms[0].submit()');
});

it('escapes field names, values and the action URL', function (): void {
    $html = AutoSubmitForm::render(
        'https://gateway.test/pay?a="><script>alert(1)</script>',
        ['"><script>x</script>' => '"><img src=x onerror=alert(1)>']
    );

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<script>x</script>')
        ->and($html)->not->toContain('<img src=x')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('&quot;&gt;');
});

it('offers a no-JavaScript fallback button with the given label', function (): void {
    $html = AutoSubmitForm::render('https://gateway.test/pay', [], 'Continue to JazzCash');

    expect($html)->toContain('<noscript>')
        ->and($html)->toContain('Continue to JazzCash');
});
