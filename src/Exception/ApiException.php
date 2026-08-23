<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

use CheckCommerce\Resources\ValidationError;

/**
 * Thrown when the API responds with an error status code.
 *
 * Exposes the parsed error payload — problem title, error code, detail,
 * correlation id and any per-field validation errors — so callers never
 * need to parse the raw response themselves.
 */
class ApiException extends \RuntimeException implements CheckCommerceException
{
    /**
     * @param int $statusCode HTTP status code of the response
     * @param string|null $errorCode error code reported by the API, e.g. `PROC:VAL-003`
     * @param string|null $title short description of the problem
     * @param string|null $detail detailed description of the problem, useful for troubleshooting
     * @param string|null $correlationId correlation id for support requests, mirrors the X-Correlation-ID header
     * @param list<ValidationError> $validationErrors per-field validation errors, if any
     * @param array<string, mixed> $responseBody decoded response body
     * @param array<string, string> $responseHeaders response headers with lowercase names
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $title = null,
        public readonly ?string $detail = null,
        public readonly ?string $correlationId = null,
        public readonly array $validationErrors = [],
        public readonly array $responseBody = [],
        public readonly array $responseHeaders = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Maps an error response to the most specific exception subclass.
     *
     * @param array<string, mixed> $body decoded response body
     * @param array<string, string> $headers response headers, lowercase names
     */
    public static function fromResponse(int $statusCode, array $body, array $headers = []): self
    {
        $title = self::stringOrNull($body['title'] ?? null);
        $code = self::stringOrNull($body['code'] ?? null);
        $detail = self::stringOrNull($body['detail'] ?? null);
        $correlationId = self::stringOrNull($body['correlationId'] ?? null)
            ?? self::stringOrNull($headers['x-correlation-id'] ?? null);

        $validationErrors = [];
        foreach ((array) ($body['errors'] ?? []) as $error) {
            if (\is_array($error)) {
                $validationErrors[] = ValidationError::fromArray($error);
            }
        }

        $message = self::buildMessage($statusCode, $title, $code, $detail, $correlationId);
        $arguments = [$message, $statusCode, $code, $title, $detail, $correlationId, $validationErrors, $body, $headers];

        return match (true) {
            401 === $statusCode => new AuthenticationException(...$arguments),
            403 === $statusCode => new AuthorizationException(...$arguments),
            404 === $statusCode => new NotFoundException(...$arguments),
            400 === $statusCode,
            422 === $statusCode => new ValidationException(...$arguments),
            429 === $statusCode => new RateLimitException(...$arguments),
            $statusCode >= 500 => new ServerException(...$arguments),
            default => new self(...$arguments),
        };
    }

    private static function buildMessage(
        int $statusCode,
        ?string $title,
        ?string $code,
        ?string $detail,
        ?string $correlationId,
    ): string {
        $parts = [];

        if (null !== $title) {
            $parts[] = $title;
        }

        if (null !== $detail && $detail !== $title) {
            $parts[] = $detail;
        }

        $message = [] === $parts
            ? \sprintf('The API responded with HTTP status %d.', $statusCode)
            : implode(': ', $parts);

        $context = [];
        if (null !== $code) {
            $context[] = 'code: '.$code;
        }
        if (null !== $correlationId) {
            $context[] = 'correlation id: '.$correlationId;
        }

        return [] === $context ? $message : \sprintf('%s (%s)', $message, implode(', ', $context));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }
}
