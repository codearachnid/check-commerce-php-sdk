<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * Paging metadata returned by list endpoints.
 */
final class Pagination extends ApiResource
{
    private function __construct(
        public readonly int $currentPage,
        public readonly int $pageSize,
        public readonly int $totalPages,
        public readonly int $totalRecords,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $pagination = new self(
            currentPage: self::intValue($data, 'currentPage') ?? 1,
            pageSize: self::intValue($data, 'pageSize') ?? 0,
            totalPages: self::intValue($data, 'totalPages') ?? 1,
            totalRecords: self::intValue($data, 'totalRecords') ?? 0,
        );
        $pagination->raw = $data;

        return $pagination;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->totalPages;
    }
}
