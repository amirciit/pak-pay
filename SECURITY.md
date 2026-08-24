# Security Policy

PakPay moves money. A bug here is not a cosmetic bug, so security reports get
priority over everything else in the backlog.

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.1.x   | ✅ |
| 1.0.x   | ✅ (security fixes only) |
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
- Safepay webhooks are additionally rejected outside a 5-minute timestamp
  window (replay protection).
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
- Making your order fulfilment idempotent: gateways retry callbacks, and a
  retried callback is a valid signed payload, not an attack.
- Setting `JAZZCASH_HOSTED_SEND_PASSWORD=false` if your JazzCash Hosted
  Checkout does not require `pp_Password` in the browser form.

## Reviewing the endpoints yourself

Endpoints and field names marked `// VERIFY` in `config/pakpay.php` and in the
drivers must be checked against each gateway's current official integration
guide before you go live. Gateways change these without notice, and a wrong
endpoint is a failed payment, not a silent one.
