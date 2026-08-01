<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * Result of creating or updating a subscription.
 */
final class SubscriptionResult extends ApiResource
{
    private function __construct(
        public readonly string $subscriptionId,
        public readonly ?string $consumerId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            subscriptionId: self::stringValue($data, 'subscriptionId') ?? '',
            consumerId: self::stringValue($data, 'consumerId'),
        );
        $result->raw = $data;

        return $result;
    }
}
