# Changelog

All notable changes to `pak-pay/pak-pay` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-09-04

A security release. It is a **major** version because two signature
constructions and one default changed — the code you call is unchanged, but the
bytes on the wire are not.

### Breaking
- **EasyPaisa and NayaPay callback signatures now sign `key=value` pairs**
  instead of concatenated values. Values-only concatenation cannot tell field
  boundaries apart — `{a:"x", b:"y&z"}` and `{a:"x&y", b:"z"}` hash identically,
  so a value could be shifted across fields without breaking the signature. If
  you matched a gateway or a stub to the old construction, re-align it (both
  schemes remain `// VERIFY` against the official guides).
- **`JAZZCASH_HOSTED_SEND_PASSWORD` now defaults to `false`**, so `pp_Password`
  is no longer rendered into the browser-visible hosted form. Set it to `true`
  explicitly if your JazzCash Hosted Checkout requires it.
- **Safepay webhook signatures are single-use.** A re-delivered webhook — a
  genuine gateway retry included — is rejected as a replay while its signature
  is remembered (`SAFEPAY_WEBHOOK_DEDUP_TTL`, default 24h). Make your handler
  idempotent and reconcile with `status()` instead of relying on retries.
- `PaymentRequest` now trims `orderId` and requires `currency` to be a 3-letter
  ISO code, which it upper-cases. A malformed currency throws
  `InvalidArgumentException` at construction instead of reaching the gateway.

### Added
- Safepay replay protection that does not depend on the timestamp header: each
  valid signature is recorded with an atomic `Cache::add`, so a captured webhook
  cannot be replayed even when no timestamp is sent or the timestamp is
  rewritten. The 5-minute freshness window still applies when one is present.
- `JAZZCASH_TXN_EXPIRY_MINUTES` (default 60) for `pp_TxnExpiryDateTime`.
- `SAFEPAY_WEBHOOK_DEDUP_TTL` (default 86400) for the dedup window.
- `toArray()` on `PaymentRequest`, `Customer`, `PaymentResult`, `PaymentStatus`
  and `RefundResult`, plus `PaymentStatus::isFinal()` — what you persist against
  an order or write to a payment log.
- `AbstractGatewayDriver::signablePairs()`, `mapCommonState()` and
  `decodeResponse()`; a non-JSON gateway reply now reports its HTTP status.
- README: an explicit warning that a valid signature proves authenticity, **not**
  the amount or the order, with idempotent reconciliation guidance.
- 40 further tests (**121 total / 232 assertions**), covering webhook dedup,
  the `key=value` signing vectors, the JazzCash secure default and expiry
  window, state mapping, custom drivers via `extend()`, and DTO serialisation.

### Changed
- Safepay no longer reports a refund as successful when the gateway accepted the
  request (HTTP 2xx) but the payload says it was rejected or cancelled.
- NayaPay, Safepay and JazzCash share one status vocabulary via
  `mapCommonState()`; JazzCash still treats an unmappable status carrying a
  response code as `failed`.

### Credits
- The webhook dedup, `key=value` signing, JazzCash default and README security
  guidance come from **@Kaleemullah007**'s
  [PR #1](https://github.com/imabbas8/pak-pay/pull/1) (`security/callback-hardening`),
  rebuilt on top of the 1.1.0 driver refactor.

## [1.1.0] - 2026-09-04

A maintenance release: no breaking changes, no new gateways. Every public API
from 1.0.0 behaves identically — the signing vectors are unchanged.

### Added
- **Continuous integration**: a GitHub Actions matrix running the suite on
  **PHP 8.0–8.4 × Laravel 8–13** (`.github/workflows/tests.yml`).
- `pakpay.redirect_ttl` (`PAKPAY_REDIRECT_TTL`, default `10` minutes) to control
  how long a stashed hosted-checkout payload stays valid. A non-positive value
  falls back to the default.
- `PakPay\PakPay\Support\AutoSubmitForm` — the single, escaped renderer for the
  self-submitting POST form, shared by the redirect route and
  `JazzCashDriver::checkoutForm()`.
- Shared driver helpers on `AbstractGatewayDriver`: `hostedRedirect()`,
  `signableString()`, `hmac()` and `decode()`.
- `CONTRIBUTING.md`, `SECURITY.md`, `.gitattributes` (a leaner Packagist dist)
  and `.editorconfig`.
- 29 further tests (**81 total / 173 assertions**) covering the redirect TTL and
  response headers, form escaping, `PaymentRequest` validation and paisa/decimal
  conversion, and the `PaymentState` vocabulary.

### Changed
- The redirect route now responds with `Cache-Control: no-store`,
  `Referrer-Policy: no-referrer` and `X-Frame-Options: DENY`, so a signed
  payment payload is never cached, framed, or leaked through a Referer header.
- JazzCash, EasyPaisa and NayaPay drivers now use the shared base-class
  plumbing instead of each carrying their own copy of the redirect stash, JSON
  decoding and HMAC signing.

## [1.0.0] - 2026-06-16

First **stable** release.

### Added
- Unified `GatewayDriver` contract: `checkout`, `charge`, `verifyCallback`, `refund`, `status`.
- Drivers: **JazzCash** (hosted + Mobile Wallet), **EasyPaisa** (hosted + Mobile Account/OTP),
  **Safepay** (Advanced Checkout + Smart Button + webhooks), **NayaPay** (hosted redirect).
- **PayFast** and **Raast** scaffolds (throw `UnsupportedFlowException` until implemented).
- Immutable DTOs (`PaymentRequest`, `PaymentResult`, `RefundResult`, `PaymentStatus`, `Customer`)
  and a `PaymentState` value object (string constants + `isSuccessful()` / `isFinal()` helpers).
- Constant-time (`hash_equals`) signature verification for every gateway, plus a timestamp
  replay window for Safepay webhooks.
- One-time, short-lived token + render route for hosted POST redirects.
- Configurable route prefix/middleware via `config('pakpay.route')`.
- `JAZZCASH_HOSTED_SEND_PASSWORD` toggle to keep the merchant password out of the
  browser-rendered hosted form.
- Pest test suite (52 tests / 98 assertions — signing vectors, tamper/replay/unsupported-flow)
  and a GitHub Actions matrix for **PHP 8.0–8.4 × Laravel 8–13**.

### Compatibility
- Runs on **PHP 8.0 → 8.4** and **Laravel 8 → 13**. The source avoids PHP 8.1-only
  syntax (native `enum`, `readonly`), so `$result->state` / `$status->state` are
  lowercase **strings** (`PaymentState::*` constants), compared with `===`.

### Security
- Callback/webhook signatures are verified before any field is trusted; mismatches throw
  `SignatureVerificationException`.
- No raw card data is captured anywhere (hosted/tokenized flows only).
