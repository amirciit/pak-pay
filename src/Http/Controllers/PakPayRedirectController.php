<?php

declare(strict_types=1);

namespace PakPay\PakPay\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Renders the self-submitting POST form used by hosted-redirect gateways.
 *
 * A driver's checkout() stashes the signed payload under a one-time token and
 * redirects here; this controller pulls it back and emits an HTML form that
 * auto-POSTs the customer to the gateway's hosted page.
 */
final class PakPayRedirectController
{
    /**
     * Render and return the auto-submitting form for the given token.
     *
     * @param string $token The one-time cache token created during checkout().
     */
    public function __invoke(string $token): Response
    {
        $stash = Cache::pull("pakpay:redirect:{$token}");

        if (! is_array($stash) || ! isset($stash['action'], $stash['fields']) || ! is_array($stash['fields'])) {
            // Token missing/expired/already used — never replay a stale payload.
            return new Response('This payment redirect has expired. Please restart your checkout.', 410);
        }

        return new Response($this->form((string) $stash['action'], $stash['fields']), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Build the self-submitting HTML form.
     *
     * @param array<string, string> $fields
     */
    private function form(string $action, array $fields): string
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
            . '<noscript><p>JavaScript is disabled. Click continue to proceed.</p>'
            . '<button type="submit">Continue to payment</button></noscript>'
            . '</form></body></html>',
            htmlspecialchars($action, ENT_QUOTES),
            $inputs
        );
    }
}
