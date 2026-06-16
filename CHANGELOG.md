# Changelog

All notable changes to `pak-pay/pak-pay` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
