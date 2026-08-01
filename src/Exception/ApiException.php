<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

use CheckCommerce\Resources\ValidationError;

/**
 * Thrown when the API responds with an error status code.
 *
 * Carries the parsed error payload — problem title, error code, detail,
 * correlation id and any per-field validation errors — so callers never
 * need to parse the raw response themselves.
 */
class ApiException extends \RuntimeException implements CheckCommerceException
{
    /**
     * @param array<string, mixed> $responseBody decoded response body
     * @param list<ValidationError> $validationErrors
     * @param array<string, string> $responseHeaders
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?string $errorCode = null,
        private readonly ?string $title = null,
        private readonly ?string $detail = null,
        private readonly ?string $correlationId = null,
        private readonly array $validationErrors = [],
        private readonly array $responseBody = [],
        private readonly array $responseHeaders = [],
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

    /** HTTP status code of the response. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Error code reported by the API, e.g. `PROC:VAL-003`. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Short description of the problem. */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /** Detailed description of the problem, useful for troubleshooting. */
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /** Correlation id for support requests, mirrors the X-Correlation-ID header. */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Per-field validation errors, if any.
     *
     * @return list<ValidationError>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * The decoded response body.
     *
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }

    /**
     * Response headers with lowercase names.
     *
     * @return array<string, string>
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    private static function buildMessage(
        int $statusCode,
        ?string $title,
        ?string $code,
        ?string $detail,
        ?string $correlationId,
    ): string {
        $parts = [];

        if (null !== $title && '' !== $title) {
            $parts[] = $title;
        }

        if (null !== $detail && '' !== $detail && $detail !== $title) {
            $parts[] = $detail;
        }

        $message = [] === $parts
            ? \sprintf('The API responded with HTTP status %d.', $statusCode)
            : implode(': ', $parts);

        $context = [];
        if (null !== $code && '' !== $code) {
            $context[] = 'code: '.$code;
        }
        if (null !== $correlationId && '' !== $correlationId) {
            $context[] = 'correlation id: '.$correlationId;
        }

        return [] === $context ? $message : \sprintf('%s (%s)', $message, implode(', ', $context));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }
}
