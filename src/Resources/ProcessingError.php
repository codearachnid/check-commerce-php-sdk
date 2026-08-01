<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * A processing failure attached to a transaction response.
 */
final class ProcessingError extends ApiResource
{
    /**
     * @param list<ValidationError> $validationErrors
     */
    private function __construct(
        public readonly ?string $code,
        public readonly ?string $detail,
        public readonly array $validationErrors,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $validationErrors = [];
        foreach ((array) ($data['validationErrors'] ?? []) as $error) {
            if (\is_array($error)) {
                $validationErrors[] = ValidationError::fromArray($error);
            }
        }

        $processingError = new self(
            code: self::stringValue($data, 'code'),
            detail: self::stringValue($data, 'detail'),
            validationErrors: $validationErrors,
        );
        $processingError->raw = $data;

        return $processingError;
    }
}
