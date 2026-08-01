<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * Result of a merchant boarding submission.
 */
final class BoardingResult extends ApiResource
{
    /**
     * @param list<array<string, mixed>> $boardedMerchants boarded merchant records as returned by the API
     * @param list<BoardingFailure> $boardingFailures
     */
    private function __construct(
        public readonly ?string $correlationId,
        public readonly array $boardedMerchants,
        public readonly array $boardingFailures,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $boardedMerchants = [];
        foreach ((array) ($data['boardedMerchants'] ?? []) as $merchant) {
            if (\is_array($merchant)) {
                $boardedMerchants[] = $merchant;
            }
        }

        $failures = [];
        foreach ((array) ($data['boardingFailures'] ?? []) as $failure) {
            if (\is_array($failure)) {
                $failures[] = BoardingFailure::fromArray($failure);
            }
        }

        $result = new self(
            correlationId: self::stringValue($data, 'correlationId'),
            boardedMerchants: $boardedMerchants,
            boardingFailures: $failures,
        );
        $result->raw = $data;

        return $result;
    }

    public function hasFailures(): bool
    {
        return [] !== $this->boardingFailures;
    }
}
