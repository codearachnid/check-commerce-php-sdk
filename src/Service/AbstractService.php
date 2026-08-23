<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Exception\InvalidArgumentException;
use CheckCommerce\Http\HttpTransport;
use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\PaginatedList;
use CheckCommerce\Resources\Pagination;

/**
 * Base class for API services.
 *
 * @internal
 */
abstract class AbstractService
{
    public function __construct(
        protected readonly HttpTransport $transport,
    ) {
    }

    /**
     * Recursively converts request parameters to JSON-ready values: backed
     * enums become their value, date objects become ISO 8601 strings.
     *
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    protected function normalizeParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            $normalized[$key] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
                $value instanceof \JsonSerializable => $value->jsonSerialize(),
                \is_array($value) => $this->normalizeParams($value),
                default => $value,
            };
        }

        return $normalized;
    }

    /**
     * Fetches one page of a list endpoint and wires up lazy access to the rest.
     *
     * List endpoints wrap their payload as `{<key>: {results: [...], pagination: {...}}}`.
     *
     * @template T
     *
     * @param string $key top-level payload key, e.g. `consumers`
     * @param \Closure(array<string, mixed>): T $hydrate builds one item from its decoded payload
     * @param array<string, mixed> $filters
     *
     * @return PaginatedList<T>
     */
    protected function paginate(
        string $path,
        string $key,
        \Closure $hydrate,
        array $filters,
        RequestOptions $options,
    ): PaginatedList {
        $response = $this->transport->request('GET', $path, query: $filters, options: $options);

        $payload = $response->data[$key] ?? [];
        $payload = \is_array($payload) ? $payload : [];

        $items = [];
        foreach ((array) ($payload['results'] ?? []) as $item) {
            if (\is_array($item)) {
                $items[] = $hydrate($item);
            }
        }

        $pagination = \is_array($payload['pagination'] ?? null)
            ? Pagination::fromArray($payload['pagination'])
            : null;

        return new PaginatedList(
            items: $items,
            pagination: $pagination,
            pageFetcher: fn (int $page): PaginatedList => $this->paginate(
                $path,
                $key,
                $hydrate,
                ['page' => $page] + $filters,
                $options,
            ),
        );
    }

    /**
     * Validates a resource id and encodes it for use as a path segment.
     *
     * @param string $label used in the error message, e.g. `consumer id`
     */
    protected function encodeId(string $id, string $label): string
    {
        if ('' === trim($id)) {
            throw new InvalidArgumentException(\sprintf('A %s is required.', $label));
        }

        return rawurlencode($id);
    }
}
