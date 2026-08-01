<?php

declare(strict_types=1);

namespace CheckCommerce\Http;

use CheckCommerce\Exception\InvalidArgumentException;

/**
 * Per-request options accepted by every service method.
 *
 * Service methods accept either an instance or a plain array:
 *
 * ```php
 * $client->consumers->retrieve($id, options: ['correlation_id' => $uuid]);
 * ```
 */
final class RequestOptions
{
    /**
     * @param string|null $correlationId sent as the X-Correlation-ID header and
     *                                   echoed back by the API for tracing
     * @param array<string, string> $headers additional request headers
     * @param array<string, mixed> $query additional query string parameters
     */
    public function __construct(
        public readonly ?string $correlationId = null,
        public readonly array $headers = [],
        public readonly array $query = [],
    ) {
    }

    /**
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public static function from(self|array|null $options): self
    {
        if ($options instanceof self) {
            return $options;
        }

        if (null === $options || [] === $options) {
            return new self();
        }

        if ([] !== $unknown = array_diff(array_keys($options), ['correlation_id', 'headers', 'query'])) {
            throw new InvalidArgumentException(\sprintf(
                'Unknown request option(s): %s. Valid options are: correlation_id, headers, query.',
                implode(', ', $unknown),
            ));
        }

        return new self(
            correlationId: $options['correlation_id'] ?? null,
            headers: $options['headers'] ?? [],
            query: $options['query'] ?? [],
        );
    }
}
