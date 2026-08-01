<?php

declare(strict_types=1);

namespace CheckCommerce\Http;

/**
 * A successful, decoded API response.
 */
final class ApiResponse
{
    /**
     * @param array<string, string> $headers response headers with lowercase names
     * @param array<string, mixed> $data decoded JSON body, empty for empty responses
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly array $data,
    ) {
    }

    public function correlationId(): ?string
    {
        $fromBody = $this->data['correlationId'] ?? null;

        if (\is_string($fromBody) && '' !== $fromBody) {
            return $fromBody;
        }

        return $this->headers['x-correlation-id'] ?? null;
    }
}
