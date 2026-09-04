# Changelog

All notable changes to `pak-pay/pak-pay` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
