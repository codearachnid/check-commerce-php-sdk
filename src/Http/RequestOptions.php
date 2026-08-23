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
     */
    public function __construct(
        public readonly ?string $correlationId = null,
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

        $options ??= [];

        if ([] !== $unknown = array_diff(array_keys($options), ['correlation_id'])) {
            throw new InvalidArgumentException(\sprintf(
                'Unknown request option(s): %s. The only valid option is correlation_id.',
                implode(', ', $unknown),
            ));
        }

        return new self(correlationId: $options['correlation_id'] ?? null);
    }
}
