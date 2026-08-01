<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * A single request validation failure.
 */
final class ValidationError extends ApiResource
{
    private function __construct(
        public readonly ?string $property,
        public readonly ?string $code,
        public readonly ?string $detail,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $error = new self(
            property: self::stringValue($data, 'property'),
            code: self::stringValue($data, 'code'),
            detail: self::stringValue($data, 'detail'),
        );
        $error->raw = $data;

        return $error;
    }
}
