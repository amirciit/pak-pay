# Security Policy

PakPay moves money. A bug here is not a cosmetic bug, so security reports get
priority over everything else in the backlog.

## Supported versions

| Version | Supported |
|---------|-----------|
| 2.0.x   | ✅ |
| 1.1.x   | ✅ (security fixes only) |
| 1.0.x   | ❌ |
| < 1.0   | ❌ |

## Reporting a vulnerability

**Do not open a public GitHub issue for a vulnerability.**

Email **[support@debugflow.com](mailto:support@debugflow.com)** with:

- what the issue is and which gateway/driver it affects,
- the steps or payload that reproduce it,
- the PakPay, PHP and Laravel versions you tested against.

You will get an acknowledgement within **72 hours** and a fix or a mitigation
plan within **14 days** for anything confirmed. Please keep the report private
until a patched release is out; credit is given in the changelog unless you
prefer to stay anonymous.

## What PakPay guarantees

- **Every** callback, return and webhook signature is verified with
  `hash_equals()` (constant time) before any field of the payload is trusted.
- Safepay webhook signatures are **single-use**: each one is recorded with an
  atomic `Cache::add` and a duplicate delivery is rejected, so a captured
  webhook cannot be replayed even when the gateway sends no timestamp header.
  When a timestamp is present, anything outside a 5-minute window is rejected
  as well.
- Signatures over form fields (EasyPaisa, NayaPay) are computed over
  `key=value` pairs, so a value cannot be shifted across a field boundary
  without changing the signature.
- The JazzCash merchant password is not rendered into the hosted form unless
  the integration explicitly opts in.
- A gateway that cannot support a flow throws `UnsupportedFlowException`
  instead of returning a fabricated success.
- Hosted-checkout payloads are stashed server-side behind a **one-time**,
  short-lived token. They never travel in a URL, and the render route replies
  `410 Gone` on reuse.
- No raw card data is captured, logged or stored anywhere in this package;
  card flows are hosted or tokenized by the gateway.

## What you are responsible for

- Keeping credentials in `.env`, never in committed config or in version control.
- Serving your return/callback routes over **HTTPS only**.
- Treating a `PaymentResult` as authoritative **only** after `verifyCallback()`
  has returned it — never trust a browser redirect's query string.
- **Reconciling before you fulfil.** A valid signature proves the payload is
  authentic — not that the right amount was paid for the right order. Check
  `$result->amount` and `$result->orderId` against the order you stored.
- Making your order fulfilment idempotent, and reconciling with `status()`
  rather than relying on gateway retries: a Safepay re-delivery is rejected as
  a replay while its signature is remembered.
- Setting `JAZZCASH_HOSTED_SEND_PASSWORD=false` if your JazzCash Hosted
  Checkout does not require `pp_Password` in the browser form.

## Reviewing the endpoints yourself

Endpoints and field names marked `// VERIFY` in `config/pakpay.php` and in the
drivers must be checked against each gateway's current official integration
guide before you go live. Gateways change these without notice, and a wrong
endpoint is a failed payment, not a silent one.
