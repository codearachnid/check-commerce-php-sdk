<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * One page of results from a list endpoint, with lazy access to further pages.
 *
 * Iterate the current page directly, or use {@see autoPagingIterator()} to
 * stream every item across all pages:
 *
 * ```php
 * foreach ($client->consumers->list()->autoPagingIterator() as $consumer) {
 *     // ...
 * }
 * ```
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class PaginatedList implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @param list<T> $items
     * @param \Closure(int): self<T>|null $pageFetcher fetches a given page number
     */
    public function __construct(
        public readonly array $items,
        public readonly ?Pagination $pagination = null,
        private readonly ?\Closure $pageFetcher = null,
    ) {
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /** Number of items on this page. */
    public function count(): int
    {
        return \count($this->items);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function hasMorePages(): bool
    {
        return null !== $this->pagination && $this->pagination->hasMorePages();
    }

    /**
     * Fetches the next page, or returns null when this is the last one.
     *
     * @return self<T>|null
     */
    public function nextPage(): ?self
    {
        if (!$this->hasMorePages() || null === $this->pageFetcher || null === $this->pagination) {
            return null;
        }

        return ($this->pageFetcher)($this->pagination->currentPage + 1);
    }

    /**
     * Iterates every item on this page and all following pages, fetching
     * pages on demand.
     *
     * @return \Generator<int, T>
     */
    public function autoPagingIterator(): \Generator
    {
        $page = $this;

        while (null !== $page) {
            yield from $page->items;

            $page = $page->nextPage();
        }
    }

    /**
     * @return array{items: list<T>, pagination: ?Pagination}
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'pagination' => $this->pagination,
        ];
    }
}
