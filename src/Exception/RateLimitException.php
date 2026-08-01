<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown on HTTP 429 — too many requests.
 */
final class RateLimitException extends ApiException
{
    /**
     * Seconds to wait before retrying, from the Retry-After header when present.
     */
    public function getRetryAfter(): ?int
    {
        $retryAfter = $this->getResponseHeaders()['retry-after'] ?? null;

        return null !== $retryAfter && is_numeric($retryAfter) ? (int) $retryAfter : null;
    }
}
