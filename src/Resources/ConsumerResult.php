<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * Result of creating or updating a consumer.
 */
final class ConsumerResult extends ApiResource
{
    private function __construct(
        public readonly string $consumerId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            consumerId: self::stringValue($data, 'consumerId') ?? '',
        );
        $result->raw = $data;

        return $result;
    }
}
