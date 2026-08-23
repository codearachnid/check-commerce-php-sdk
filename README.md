# Check Commerce PHP SDK

A modern PHP SDK for the [Check Commerce (OBP Link) API](https://sandbox.checkcommerce.com/api/docs/index.html) — ACH, RTP, paper check and IAT payments, stored consumers, recurring subscriptions, hosted payment pages, batch processing and merchant boarding.

- **Typed end to end** — native enums for payment types, transaction statuses and schedules; immutable response objects with real properties.
- **Zero-friction auth** — bearer tokens are acquired, cached and refreshed automatically; pluggable token storage for sharing tokens across processes.
- **Safe retries** — exponential backoff with jitter for rate limits, server errors and network failures, applied only where a retry cannot double-charge.
- **Rich errors** — every API failure maps to a typed exception carrying the error code, detail, correlation id and per-field validation errors.
- **Framework agnostic** — ships with Guzzle, accepts any PSR-18 client; no framework required.
- **Forward compatible** — new API fields and enum values never break the SDK; everything stays reachable through the raw payload.

## Requirements

- PHP 8.1+

## Installation

```bash
composer require codearachnid/check-commerce-php-sdk
```

[Guzzle](https://github.com/guzzle/guzzle) is installed as the default HTTP client; any other [PSR-18](https://www.php-fig.org/psr/psr-18/) client can be [injected](#custom-http-client) instead.

## Quick start

```php
use CheckCommerce\CheckCommerceClient;

$client = new CheckCommerceClient([
    'api_key' => getenv('CHECK_COMMERCE_API_KEY'),
    'merchant_number' => getenv('CHECK_COMMERCE_MERCHANT_NUMBER'),
    'environment' => 'sandbox',
]);

// The API requires the merchant number in every transaction payload;
// reuse the one the client was configured with:
$result = $client->transactions->debit([
    'merchantNumber' => $client->config->merchantNumber,
    'amount' => 42.50,
    'referenceNumber' => 'INV-1001',
    'consumerInfo' => [
        'name' => 'Jane Doe',
        'bankAccountNumber' => '1234567890',
        'bankRoutingNumber' => 121000248,
    ],
]);

echo $result->transactionId;    // 123456789
echo $result->status->value;    // "Processed"
```

Or configure entirely from the environment:

```php
// Reads CHECK_COMMERCE_API_KEY, CHECK_COMMERCE_MERCHANT_NUMBER, and
// CHECK_COMMERCE_ENVIRONMENT ("production" or "sandbox", default production).
$client = CheckCommerceClient::fromEnv();

// Anything not in the environment can be passed as an override:
$client = CheckCommerceClient::fromEnv(['timeout' => 60, 'max_retries' => 3]);
```

The environment defaults to production; every option is documented on `Configuration::fromArray()`:

```php
use CheckCommerce\CheckCommerceClient;
use CheckCommerce\Environment;
use CheckCommerce\Scope;

$client = new CheckCommerceClient([
    'api_key' => getenv('CHECK_COMMERCE_API_KEY'),
    'merchant_number' => getenv('CHECK_COMMERCE_MERCHANT_NUMBER'),
    'environment' => Environment::Sandbox,
    'scopes' => [Scope::Transactions, Scope::HostedPages],
    'timeout' => 30,
    'max_retries' => 2,
]);
```

## Authentication

You never call the authentication endpoint yourself. The first API call requests a bearer token, caches it, refreshes it shortly before expiry, and transparently re-authenticates once if the API rejects a token mid-flight.

```php
// Optional: validate credentials eagerly (e.g. at deploy time)
$token = $client->authenticate();
echo $token->expiresAt->format(DATE_ATOM);
```

By default tokens live in memory for the current process. For long-running or multi-process apps, supply a shared store:

```php
use CheckCommerce\Auth\AccessToken;
use CheckCommerce\Auth\TokenStoreInterface;

final class CacheTokenStore implements TokenStoreInterface
{
    public function __construct(private \Psr\SimpleCache\CacheInterface $cache) {}

    public function get(string $key): ?AccessToken
    {
        $data = $this->cache->get($key);
        return is_array($data) ? AccessToken::fromArray($data) : null;
    }

    public function put(string $key, AccessToken $token): void
    {
        $this->cache->set($key, $token->toArray());
    }

    public function forget(string $key): void
    {
        $this->cache->delete($key);
    }
}

$client = new CheckCommerceClient($config, tokenStore: new CacheTokenStore($cache));
```

## Transactions

```php
use CheckCommerce\Enums\PaymentType;
use CheckCommerce\Enums\TransactionType;

$mid = $client->config->merchantNumber; // as configured (e.g. from CHECK_COMMERCE_MERCHANT_NUMBER)

// Sugar for the common operations — sets transactionType for you:
$client->transactions->debit([...]);
$client->transactions->credit([...]);
$client->transactions->void(['merchantNumber' => $mid, 'originalTransaction' => ['transactionId' => 123456789]]);
$client->transactions->refund(['merchantNumber' => $mid, 'originalTransaction' => ['referenceNumber' => 'INV-1001']]);

// Full control — any transaction type, any payment rail:
$client->transactions->create(
    ['merchantNumber' => $mid, 'transactionType' => TransactionType::Prenote, /* ... */],
    PaymentType::Rtp,
);

// Status lookups:
$status = $client->transactions->status(transactionId: 123456789);
$status = $client->transactions->status(referenceNumber: 'INV-1001', requestType: PaymentType::Ach);
$auth   = $client->transactions->authStatus(transactionId: 123456789);

if ($status->isDeclined()) {
    echo $status->processingFailure?->detail; // "Threshold Exceeded"
}
```

## Consumers

Store consumer bank details once, then reference them by id in transactions and subscriptions:

```php
$created = $client->consumers->create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'bankAccountNumber' => '1234567890',
    'bankRoutingNumber' => 121000248,
]);

$consumer = $client->consumers->retrieve($created->consumerId);
$client->consumers->update($created->consumerId, ['phoneNumber' => '5125551234']);

// Charge a stored consumer:
$client->transactions->debit([
    'merchantNumber' => $client->config->merchantNumber,
    'amount' => 42.50,
    'consumerInfo' => ['consumerId' => $created->consumerId],
]);
```

List endpoints return one page plus lazy access to the rest — `autoPagingIterator()` streams every record and fetches pages on demand:

```php
$page = $client->consumers->list(['city' => 'Austin', 'pageSize' => 100]);

foreach ($page->autoPagingIterator() as $consumer) {
    echo $consumer->name, "\n";
}
```

## Subscriptions

```php
use CheckCommerce\Enums\SubscriptionEndCode;
use CheckCommerce\Enums\SubscriptionStatus;
use CheckCommerce\Enums\TransactionType;

$created = $client->subscriptions->create([
    'startTime' => new DateTimeImmutable('first day of next month'),
    'amount' => 25.00,
    'schCode' => 'Monthly:1',
    'endCode' => SubscriptionEndCode::Indefinite,
    'transactionType' => TransactionType::Debit,
    'status' => SubscriptionStatus::Active,
    'consumerInfo' => ['consumerId' => $consumerId],
]);

$subscription = $client->subscriptions->retrieve($created->subscriptionId);
$client->subscriptions->update($created->subscriptionId, ['amount' => 30.00]);

foreach ($client->subscriptions->list(['includeSuspended' => true]) as $subscription) {
    echo $subscription->scheduleCode, ' → ', $subscription->status?->value, "\n";
}
```

## Hosted payment pages

```php
$link = $client->hostedPages->createLink([
    'customer' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    'order' => [
        'subTotal' => 89.95,
        'tax' => 10.00,
        'total' => 99.95,
        'returnURL' => 'https://example.com/thanks',
    ],
    'orderItems' => [
        ['name' => 'Widget', 'quantity' => 1, 'price' => 89.95],
    ],
]);

header('Location: '.$link->url);
```

## Batches

```php
use CheckCommerce\Enums\FileDelimiter;

// JSON batch:
$mid = $client->config->merchantNumber;
$batch = $client->batches->submit([
    ['merchantNumber' => $mid, 'transactionType' => 'Debit', 'amount' => 42.50, 'consumerInfo' => [...]],
    ['merchantNumber' => $mid, 'transactionType' => 'Debit', 'amount' => 19.99, 'consumerInfo' => [...]],
]);

// File upload:
$batch = $client->batches->uploadFile('/path/to/batch.csv', FileDelimiter::Comma);

// Poll until processed:
$status = $client->batches->status($batch->batchId);
echo $status->status->value; // "Pending" | "Processing" | "Processed" | "Declined"
```

## Merchant boarding

```php
$result = $client->boarding->board(['merchants' => [/* boarding records */]]);

foreach ($result->boardingFailures as $failure) {
    echo $failure->companyName, ': ', $failure->processingFailure?->detail, "\n";
}
```

## Error handling

Every SDK exception implements `CheckCommerce\Exception\CheckCommerceException`; API errors map to a typed hierarchy:

| Exception | When |
| --- | --- |
| `ValidationException` | 400/422 — invalid request, has per-field errors |
| `AuthenticationException` | 401 — bad API key, merchant number, or token |
| `AuthorizationException` | 403 — missing scope or disabled feature |
| `NotFoundException` | 404 — resource does not exist |
| `RateLimitException` | 429 — too many requests (`getRetryAfter()`) |
| `ServerException` | 5xx — API-side failure |
| `ApiException` | any other error status (base class of the above) |
| `TransportException` | network failure, no API response received |
| `InvalidArgumentException` | misuse detected before a request is sent |

```php
use CheckCommerce\Exception\ApiException;
use CheckCommerce\Exception\ValidationException;

try {
    $client->transactions->debit([...]);
} catch (ValidationException $e) {
    foreach ($e->validationErrors as $error) {
        echo $error->property, ': ', $error->detail, "\n";
    }
} catch (ApiException $e) {
    // Everything you need for a support ticket:
    log_error($e->getMessage(), [
        'status' => $e->statusCode,
        'code' => $e->errorCode,
        'correlation_id' => $e->correlationId,
    ]);
}
```

Pass your own correlation id to trace a request end to end:

```php
$client->transactions->debit([...], options: ['correlation_id' => $uuid]);
```

## Retries

Failed requests are retried with exponential backoff and jitter, up to `max_retries` (default 2):

- **429** responses are retried for every method (the request was rejected, not processed) and honor `Retry-After`.
- **5xx** responses and **network failures** are retried for `GET` requests only — a write that may have reached the API is never blindly resent.

Set `'max_retries' => 0` to disable retries entirely.

## Forward compatibility

Typed properties cover the documented API. Anything the API adds later remains reachable — response objects expose the full payload:

```php
$consumer = $client->consumers->retrieve($id);
$consumer['brandNewField'];   // array access hits the raw payload
$consumer->toArray();         // the whole decoded response
```

Unknown enum values parse to `null` instead of throwing; the raw value stays available (e.g. `TransactionResult::$statusRaw`).

## Custom HTTP client

By default the SDK builds a Guzzle client from the configured `timeout` and `connect_timeout`. Inject any PSR-18 client and PSR-17 factories instead — useful for proxies, middleware, or tests:

```php
$client = new CheckCommerceClient(
    $config,
    httpClient: $myPsr18Client,
    requestFactory: $myPsr17Factory,
    streamFactory: $myPsr17Factory,
);
```

The test suite ships a `FakeHttpClient` pattern you can copy for your own integration tests — no HTTP mocking library required.

## Laravel

This package is intentionally framework-free. For Laravel applications, use the companion package [`codearachnid/check-commerce-laravel-sdk`](https://packagist.org/packages/codearachnid/check-commerce-laravel-sdk) — it wraps this SDK with a service provider, publishable config mapping the `CHECK_COMMERCE_*` environment variables, a `CheckCommerce` facade, a cache-backed token store shared across workers, and a testing fake for feature tests:

```bash
composer require codearachnid/check-commerce-laravel-sdk
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run the suite with:

```bash
composer install
composer test   # PHPUnit
composer stan   # PHPStan (level 8)
```

## License

Released under the [MIT License](LICENSE).
