<?php

declare(strict_types=1);

namespace CheckCommerce;

use CheckCommerce\Exception\InvalidArgumentException;

/**
 * Immutable client configuration.
 *
 * ```php
 * $config = new Configuration(
 *     apiKey: getenv('CHECK_COMMERCE_API_KEY'),
 *     merchantNumber: getenv('CHECK_COMMERCE_MERCHANT_NUMBER'),
 *     environment: Environment::Sandbox,
 * );
 * ```
 */
final class Configuration
{
    public readonly string $baseUrl;

    public readonly Environment $environment;

    /** @var list<string> */
    public readonly array $scopes;

    /**
     * @param string $apiKey API key issued for the merchant account
     * @param string $merchantNumber merchant number (MID) associated with the API key
     * @param Environment|string $environment target environment, defaults to production
     * @param string|null $baseUrl overrides the environment base URL when set
     * @param list<Scope|string> $scopes scopes to request for issued tokens; empty
     *                                   requests every scope available to the API key
     * @param float $timeout request timeout in seconds, applied to the default HTTP client
     * @param float $connectTimeout connection timeout in seconds, applied to the default HTTP client
     * @param int $maxRetries maximum number of retries for retryable requests
     * @param int $tokenExpiryMarginSeconds tokens are refreshed this many seconds
     *                                      before their reported expiry
     */
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $apiKey,
        public readonly string $merchantNumber,
        Environment|string $environment = Environment::Production,
        ?string $baseUrl = null,
        array $scopes = [],
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly int $maxRetries = 2,
        public readonly int $tokenExpiryMarginSeconds = 60,
    ) {
        if ('' === trim($apiKey)) {
            throw new InvalidArgumentException('An API key is required.');
        }

        if ('' === trim($merchantNumber)) {
            throw new InvalidArgumentException('A merchant number is required.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must be zero or greater.');
        }

        $this->environment = \is_string($environment)
            ? (Environment::tryFrom($environment) ?? throw new InvalidArgumentException(\sprintf(
                'Unknown environment "%s". Expected "production" or "sandbox".',
                $environment,
            )))
            : $environment;

        $this->baseUrl = rtrim($baseUrl ?? $this->environment->baseUrl(), '/');

        $this->scopes = array_values(array_map(
            static fn (Scope|string $scope): string => $scope instanceof Scope ? $scope->value : $scope,
            $scopes,
        ));
    }

    /**
     * Builds a configuration from an associative array.
     *
     * Accepted keys mirror the constructor parameters in snake_case:
     * `api_key`, `merchant_number`, `environment`, `base_url`, `scopes`,
     * `timeout`, `connect_timeout`, `max_retries`, `token_expiry_margin_seconds`.
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        $known = [
            'api_key', 'merchant_number', 'environment', 'base_url', 'scopes',
            'timeout', 'connect_timeout', 'max_retries', 'token_expiry_margin_seconds',
        ];

        if ([] !== $unknown = array_diff(array_keys($options), $known)) {
            throw new InvalidArgumentException(\sprintf(
                'Unknown configuration option(s): %s. Valid options are: %s.',
                implode(', ', $unknown),
                implode(', ', $known),
            ));
        }

        return new self(
            apiKey: (string) ($options['api_key'] ?? ''),
            merchantNumber: (string) ($options['merchant_number'] ?? ''),
            environment: $options['environment'] ?? Environment::Production,
            baseUrl: $options['base_url'] ?? null,
            scopes: $options['scopes'] ?? [],
            timeout: (float) ($options['timeout'] ?? 30.0),
            connectTimeout: (float) ($options['connect_timeout'] ?? 10.0),
            maxRetries: (int) ($options['max_retries'] ?? 2),
            tokenExpiryMarginSeconds: (int) ($options['token_expiry_margin_seconds'] ?? 60),
        );
    }
}
