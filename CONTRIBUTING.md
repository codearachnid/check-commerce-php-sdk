# Contributing

Thanks for helping improve the Check Commerce PHP SDK.

## Setup

```bash
git clone <your-fork>
cd check-commerce-phpsdk
composer install
```

## Checks

All of these must pass before a pull request is merged (CI runs them on PHP 8.1–8.4):

```bash
composer validate --strict
composer test   # PHPUnit
composer stan   # PHPStan, level 8
```

## Guidelines

- Every code change needs a test. The suite uses a queued fake PSR-18 client
  (`tests/Support/FakeHttpClient.php`) — no network access, no mocking library.
- Public API surface (client, services, resources, exceptions) is documented
  with PHPDoc and, where behavior is user-facing, in the README.
- Response resources must stay forward compatible: typed properties for
  documented fields, raw payload access for everything else, and lenient enum
  parsing (`fromApi()`), so new API fields or enum values never throw.
- Follow PSR-12 with `declare(strict_types=1);` in every file.
- Never retry a write that may have reached the API. Read
  `HttpTransport::canRetryStatus()` before touching retry behavior.

## Releases

Versions follow SemVer. Update `CheckCommerceClient::VERSION` and
`CHANGELOG.md` in the release commit.
