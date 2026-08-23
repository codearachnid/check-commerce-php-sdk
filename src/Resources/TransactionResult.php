<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

use CheckCommerce\Enums\PaymentType;
use CheckCommerce\Enums\TransactionStatus;

/**
 * Result of submitting a transaction or querying its status.
 */
final class TransactionResult extends ApiResource
{
    /**
     * @param string|int|null $statusRaw the status exactly as returned by the
     *                                   API, useful when a new status is not yet
     *                                   mapped to {@see TransactionStatus}
     * @param list<string> $notes
     */
    private function __construct(
        public readonly ?string $correlationId,
        public readonly ?int $transactionId,
        public readonly ?TransactionStatus $status,
        public readonly string|int|null $statusRaw,
        public readonly ?string $consumerInfoId,
        public readonly array $notes,
        public readonly ?ProcessingError $processingFailure,
        public readonly ?PaymentType $paymentType,
        public readonly ?PaperCheckDetails $paperCheckDetails,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusRaw = $data['status'] ?? null;
        $statusRaw = \is_string($statusRaw) || \is_int($statusRaw) ? $statusRaw : null;

        $paymentTypeRaw = $data['paymentType'] ?? null;
        $processingFailure = self::arrayValue($data, 'processingFailure');
        $paperCheckDetails = self::arrayValue($data, 'paperCheckDetails');

        $result = new self(
            correlationId: self::stringValue($data, 'correlationId'),
            transactionId: self::intValue($data, 'transactionId'),
            status: TransactionStatus::fromApi($statusRaw),
            statusRaw: $statusRaw,
            consumerInfoId: self::stringValue($data, 'consumerInfoId'),
            notes: self::stringList($data, 'notes'),
            processingFailure: null !== $processingFailure ? ProcessingError::fromArray($processingFailure) : null,
            paymentType: \is_string($paymentTypeRaw) || \is_int($paymentTypeRaw) ? PaymentType::fromApi($paymentTypeRaw) : null,
            paperCheckDetails: null !== $paperCheckDetails ? PaperCheckDetails::fromArray($paperCheckDetails) : null,
        );
        $result->raw = $data;

        return $result;
    }

    public function isDeclined(): bool
    {
        return TransactionStatus::Declined === $this->status
            || TransactionStatus::DeclinedDownloaded === $this->status;
    }
}
