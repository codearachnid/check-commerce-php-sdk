<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

use CheckCommerce\Enums\PaperCheckStatus;

/**
 * Paper-check specific details attached to a transaction response.
 */
final class PaperCheckDetails extends ApiResource
{
    private function __construct(
        public readonly ?string $checkTransactionId,
        public readonly ?string $checkNumber,
        public readonly ?\DateTimeImmutable $checkPaymentDate,
        public readonly ?PaperCheckStatus $transactionStatus,
        public readonly ?string $trackingStatus,
        public readonly ?string $trackingNumber,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $details = new self(
            checkTransactionId: self::stringValue($data, 'checkTransactionId'),
            checkNumber: self::stringValue($data, 'checkNumber'),
            checkPaymentDate: self::dateValue($data, 'checkPaymentDate'),
            transactionStatus: PaperCheckStatus::fromApi($data['transactionStatus'] ?? null),
            trackingStatus: self::stringValue($data, 'trackingStatus'),
            trackingNumber: self::stringValue($data, 'trackingNumber'),
        );
        $details->raw = $data;

        return $details;
    }
}
