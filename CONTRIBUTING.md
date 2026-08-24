# Contributing to PakPay

Thanks for helping out. Bug reports, gateway corrections and new drivers are
all welcome.

## Getting set up

```bash
git clone https://github.com/imabbas8/pak-pay.git
cd pak-pay
composer install
composer test
```

The suite runs on Pest against a Testbench Laravel app — no credentials, no
network calls, no sandbox account needed. Every HTTP call is faked.

## Before you open a pull request

1. `composer test` passes.
2. New behaviour comes with a test. Signing changes come with a **known
   vector** test (see `tests/Unit/*SignatureTest.php`) — a signature test that
   only compares the driver against itself proves nothing.
3. The code stays PHP **8.0**-compatible: no native `enum`, no `readonly`, no
   `never` return type. That is why `PaymentState` is a class of string
   constants rather than an enum.
4. Public methods and properties carry docblocks in the style of the
   surrounding code.
5. Nothing is added to `composer.json`'s `require` without a good reason —
   this package deliberately depends only on Illuminate components.

## Adding or fixing a gateway driver

- Extend `AbstractGatewayDriver` and implement **only** the flows the gateway
  actually supports. Everything else must keep inheriting
  `UnsupportedFlowException` — never fake a result.
- Verify incoming signatures with `hash_equals()`, always, before reading any
  field of the payload.
- Reuse the shared helpers on the base class (`signableString()`, `hmac()`,
  `decode()`, `hostedRedirect()`, `endpoint()`, `requireConfig()`) instead of
  re-implementing them per driver.
- Register the driver with a `create<Name>Driver()` method on `PakPayManager`
  and add its config block to `config/pakpay.php`.
- Mark anything you could not confirm against official documentation with a
  `// VERIFY` comment, exactly as the existing drivers do.

## Reporting a security issue

Do not open a public issue. See [SECURITY.md](SECURITY.md).

## Commit messages

Short, imperative subject lines, prefixed by type — for example
`feat: add PayFast hosted checkout`, `fix: normalise EasyPaisa return codes`,
`test: cover NayaPay replay rejection`.
