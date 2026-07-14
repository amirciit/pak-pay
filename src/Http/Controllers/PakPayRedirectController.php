<?php

declare(strict_types=1);

namespace PakPay\PakPay\Http\Controllers;

use PakPay\PakPay\Support\AutoSubmitForm;
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

        $html = AutoSubmitForm::render((string) $stash['action'], $stash['fields']);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // A signed payment payload must never be cached or archived.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
        ]);
    }
}
