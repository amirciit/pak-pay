<?php

declare(strict_types=1);

namespace PakPay\PakPay\Support;

/**
 * Renders the self-submitting POST form that carries a customer to a hosted
 * payment page.
 *
 * Hosted gateways (JazzCash, EasyPaisa, NayaPay) expect a browser form POST,
 * which an HTTP 302 cannot perform. Both the package redirect route and the
 * drivers that expose their own form HTML render it through here, so the markup
 * and — more importantly — the escaping live in exactly one place.
 */
final class AutoSubmitForm
{
    /**
     * Build a complete HTML document that POSTs the given fields on load.
     *
     * Every attribute value is escaped with ENT_QUOTES; the fields come from a
     * signed gateway payload, but they are still rendered into markup and are
     * treated as untrusted here.
     *
     * @param string                $action The gateway URL to POST to.
     * @param array<string, string> $fields Hidden form fields.
     * @param string                $label  Label of the no-JavaScript fallback button.
     */
    public static function render(string $action, array $fields, string $label = 'Continue to payment'): string
    {
        $inputs = '';

        foreach ($fields as $name => $value) {
            $inputs .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                htmlspecialchars((string) $name, ENT_QUOTES),
                htmlspecialchars((string) $value, ENT_QUOTES)
            );
        }

        return sprintf(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Redirecting to payment…</title></head>'
            . '<body onload="document.forms[0].submit()">'
            . '<form method="POST" action="%s">%s'
            . '<noscript><p>JavaScript is disabled. Click the button below to continue.</p>'
            . '<button type="submit">%s</button></noscript>'
            . '</form></body></html>',
            htmlspecialchars($action, ENT_QUOTES),
            $inputs,
            htmlspecialchars($label, ENT_QUOTES)
        );
    }
}
