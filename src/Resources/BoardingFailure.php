<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * A merchant that failed to board, with the reason.
 */
final class BoardingFailure extends ApiResource
{
    private function __construct(
        public readonly ?string $companyName,
        public readonly ?ProcessingError $processingFailure,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $processingFailure = self::arrayValue($data, 'processingFailure');

        $failure = new self(
            companyName: self::stringValue($data, 'companyName'),
            processingFailure: null !== $processingFailure ? ProcessingError::fromArray($processingFailure) : null,
        );
        $failure->raw = $data;

        return $failure;
    }
}
