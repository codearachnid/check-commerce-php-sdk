<?php

declare(strict_types=1);

namespace CheckCommerce;

use CheckCommerce\Auth\AccessToken;
use CheckCommerce\Auth\Authenticator;
use CheckCommerce\Auth\InMemoryTokenStore;
use CheckCommerce\Auth\TokenStoreInterface;
use CheckCommerce\Http\HttpClientFactory;
use CheckCommerce\Http\HttpTransport;
use CheckCommerce\Service\BatchService;
use CheckCommerce\Service\BoardingService;
use CheckCommerce\Service\ConsumerService;
use CheckCommerce\Service\HostedPageService;
use CheckCommerce\Service\SubscriptionService;
use CheckCommerce\Service\TransactionService;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point to the Check Commerce API.
 *
 * ```php
 * use CheckCommerce\CheckCommerceClient;
 * use CheckCommerce\Environment;
 *
 * $client = new CheckCommerceClient([
 *     'api_key' => getenv('CHECK_COMMERCE_API_KEY'),
 *     'merchant_number' => getenv('CHECK_COMMERCE_MERCHANT_NUMBER'),
 *     'environment' => Environment::Sandbox,
 * ]);
 *
 * $result = $client->transactions->debit([
 *     'merchantNumber' => $client->config->merchantNumber,
 *     'amount' => 42.50,
 *     'consumerInfo' => [
 *         'name' => 'Jane Doe',
 *         'bankAccountNumber' => '1234567890',
 *         'bankRoutingNumber' => 121000248,
 *     ],
 * ]);
 * ```
 *
 * Authentication is handled automatically: a bearer token is requested on the
 * first API call, cached, refreshed before expiry, and re-acquired once if the
 * API rejects it.
 */
final class CheckCommerceClient
{
    public const VERSION = '0.1.0';

    public readonly Configuration $config;

    public readonly TransactionService $transactions;

    public readonly BatchService $batches;

    public readonly ConsumerService $consumers;

    public readonly SubscriptionService $subscriptions;

    public readonly HostedPageService $hostedPages;

    public readonly BoardingService $boarding;

    private readonly Authenticator $authenticator;

    /**
     * @param Configuration|array<string, mixed> $config a {@see Configuration}
     *                                                   or an options array accepted by {@see Configuration::fromArray()}
     * @param ClientInterface|null $httpClient any PSR-18 client; discovered
     *                                         automatically when omitted
     * @param TokenStoreInterface|null $tokenStore bearer token storage; defaults
     *                                             to per-process in-memory storage
     */
    public function __construct(
        Configuration|array $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?TokenStoreInterface $tokenStore = null,
    ) {
        $this->config = \is_array($config) ? Configuration::fromArray($config) : $config;

        $transport = new HttpTransport(
            $this->config,
            $httpClient ?? HttpClientFactory::create($this->config),
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
        );

        $this->authenticator = new Authenticator(
            $transport,
            $this->config,
            $tokenStore ?? new InMemoryTokenStore(),
        );
        $transport->setAuthenticator($this->authenticator);

        $this->transactions = new TransactionService($transport);
        $this->batches = new BatchService($transport);
        $this->consumers = new ConsumerService($transport);
        $this->subscriptions = new SubscriptionService($transport);
        $this->hostedPages = new HostedPageService($transport);
        $this->boarding = new BoardingService($transport);
    }

    /**
     * Named constructor for the sandbox environment.
     *
     * @param list<Scope|string> $scopes
     */
    public static function sandbox(
        #[\SensitiveParameter]
        string $apiKey,
        string $merchantNumber,
        array $scopes = [],
    ): self {
        return new self(new Configuration(
            apiKey: $apiKey,
            merchantNumber: $merchantNumber,
            environment: Environment::Sandbox,
            scopes: $scopes,
        ));
    }

    /**
     * Named constructor for the production environment.
     *
     * @param list<Scope|string> $scopes
     */
    public static function production(
        #[\SensitiveParameter]
        string $apiKey,
        string $merchantNumber,
        array $scopes = [],
    ): self {
        return new self(new Configuration(
            apiKey: $apiKey,
            merchantNumber: $merchantNumber,
            environment: Environment::Production,
            scopes: $scopes,
        ));
    }

    /**
     * Builds a client from environment variables.
     *
     * Reads `CHECK_COMMERCE_API_KEY`, `CHECK_COMMERCE_MERCHANT_NUMBER`, and
     * optionally `CHECK_COMMERCE_ENVIRONMENT` (`production` or `sandbox`,
     * defaults to production). Both credentials are required and missing ones
     * are reported by variable name.
     *
     * ```php
     * $client = CheckCommerceClient::fromEnv();
     * ```
     *
     * @param array<string, mixed> $overrides {@see Configuration::fromArray()}
     *                                        options that take precedence over
     *                                        the environment
     */
    public static function fromEnv(array $overrides = []): self
    {
        $fromEnvironment = array_filter([
            'api_key' => self::envString('CHECK_COMMERCE_API_KEY'),
            'merchant_number' => self::envString('CHECK_COMMERCE_MERCHANT_NUMBER'),
            'environment' => self::envString('CHECK_COMMERCE_ENVIRONMENT'),
        ], static fn (?string $value): bool => null !== $value);

        $options = $overrides + $fromEnvironment;

        foreach ([
            'api_key' => 'CHECK_COMMERCE_API_KEY',
            'merchant_number' => 'CHECK_COMMERCE_MERCHANT_NUMBER',
        ] as $option => $variable) {
            if (!\is_string($options[$option] ?? null) || '' === trim($options[$option])) {
                throw new Exception\InvalidArgumentException(\sprintf(
                    'The %s environment variable is not set.',
                    $variable,
                ));
            }
        }

        return new self(Configuration::fromArray($options));
    }

    private static function envString(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? $value : null;
    }

    /**
     * Forces authentication now and returns the issued token.
     *
     * Calling this is optional — every request authenticates on demand — but
     * it is useful for validating credentials or warming a shared token store.
     */
    public function authenticate(): AccessToken
    {
        return $this->authenticator->refresh();
    }
}
