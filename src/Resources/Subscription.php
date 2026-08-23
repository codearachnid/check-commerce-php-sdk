<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

use CheckCommerce\Enums\SubscriptionEndCode;
use CheckCommerce\Enums\SubscriptionStatus;
use CheckCommerce\Enums\TransactionType;

/**
 * A recurring payment subscription.
 */
final class Subscription extends ApiResource
{
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $group,
        public readonly ?\DateTimeImmutable $startTime,
        public readonly ?float $amount,
        public readonly ?float $amountTotal,
        public readonly ?string $scheduleCode,
        public readonly ?SubscriptionEndCode $endCode,
        public readonly ?\DateTimeImmutable $endTime,
        public readonly ?string $notes,
        public readonly ?TransactionType $transactionType,
        public readonly ?SubscriptionStatus $status,
        public readonly ?Consumer $consumerInfo,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $consumerInfo = self::arrayValue($data, 'consumerInfo');

        $subscription = new self(
            id: self::stringValue($data, 'id'),
            group: self::stringValue($data, 'group'),
            startTime: self::dateValue($data, 'startTime'),
            amount: self::floatValue($data, 'amount'),
            amountTotal: self::floatValue($data, 'amountTotal'),
            scheduleCode: self::stringValue($data, 'schCode'),
            endCode: SubscriptionEndCode::fromApi($data['endCode'] ?? null),
            endTime: self::dateValue($data, 'endTime'),
            notes: self::stringValue($data, 'notes'),
            transactionType: TransactionType::fromApi($data['transactionType'] ?? null),
            status: SubscriptionStatus::fromApi($data['status'] ?? null),
            consumerInfo: null !== $consumerInfo ? Consumer::fromArray($consumerInfo) : null,
        );
        $subscription->raw = $data;

        return $subscription;
    }
}
