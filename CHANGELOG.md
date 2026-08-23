# Changelog

All notable changes to this package are documented in this file, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `ApiException` exposes its fields as public readonly properties
  (`$statusCode`, `$errorCode`, `$title`, `$detail`, `$correlationId`,
  `$validationErrors`, `$responseBody`, `$responseHeaders`), matching the
  response resources; the corresponding `get*()` accessors are removed.
- Guzzle is now a direct dependency and the default HTTP client; `php-http/discovery`
  is no longer used. Any PSR-18 client can still be injected into `CheckCommerceClient`.

### Removed

- `PaginatedList::first()`, `isEmpty()`, `count()` and `jsonSerialize()`;
  use `$page->items` directly. `PaginatedList` remains iterable.
- `TransactionResult::hasProcessingFailure()`; check
  `$result->processingFailure` for null.
- `CheckCommerceClient::sandbox()` and `CheckCommerceClient::production()`;
  pass `'environment' => 'sandbox'` (or `Environment::Sandbox`) to the
  constructor, or use `fromEnv()`.
- `CheckCommerce\Http\HttpClientFactory` (internal).

## [0.1.0] - 2026-08-01

### Added

- `CheckCommerceClient::fromEnv()` for configuration via the
  `CHECK_COMMERCE_API_KEY`, `CHECK_COMMERCE_MERCHANT_NUMBER` and
  `CHECK_COMMERCE_ENVIRONMENT` environment variables.
- `CheckCommerceClient` entry point with sandbox/production environments and
  automatic bearer token management (lazy fetch, caching, expiry refresh,
  single re-auth on rejection) with pluggable token stores.
- Services covering the full API surface: transactions (ACH, RTP, paper check,
  IAT), consumers, subscriptions, batches (JSON and file upload), hosted
  payment pages, and merchant boarding.
- Typed response resources with forward-compatible raw payload access, native
  enums for all API enumerations (accepting both string and ordinal forms),
  and auto-paginating list results.
- Typed exception hierarchy carrying error codes, correlation ids and
  per-field validation errors.
- Safe retry policy: exponential backoff with jitter for 429 (all methods,
  honoring Retry-After) and for 5xx/network failures (reads only).
- PSR-18/PSR-17 HTTP abstraction with auto-discovery and Guzzle-aware
  timeout defaults.
