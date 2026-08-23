<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

use CheckCommerce\Enums\BatchStatus;

/**
 * Status of a submitted transaction batch.
 */
final class BatchResult extends ApiResource
{
    /**
     * @param list<string> $errors
     */
    private function __construct(
        public readonly ?int $batchId,
        public readonly ?BatchStatus $status,
        public readonly array $errors,
        public readonly ?float $totalAmount,
        public readonly ?int $transactionCount,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            batchId: self::intValue($data, 'batchId'),
            status: BatchStatus::fromApi($data['batchStatus'] ?? null),
            errors: self::stringList($data, 'errors'),
            totalAmount: self::floatValue($data, 'totalAmount'),
            transactionCount: self::intValue($data, 'transactionCount'),
        );
        $result->raw = $data;

        return $result;
    }
}
