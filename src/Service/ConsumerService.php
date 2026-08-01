<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Enums\SortDirection;
use CheckCommerce\Exception\InvalidArgumentException;
use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\Consumer;
use CheckCommerce\Resources\ConsumerResult;
use CheckCommerce\Resources\PaginatedList;
use CheckCommerce\Resources\Pagination;

/**
 * Store and manage consumer profiles for reuse across transactions.
 */
final class ConsumerService extends AbstractService
{
    /**
     * Creates a consumer.
     *
     * ```php
     * $result = $client->consumers->create([
     *     'name' => 'Jane Doe',
     *     'email' => 'jane@example.com',
     *     'bankAccountNumber' => '1234567890',
     *     'bankRoutingNumber' => 121000248,
     * ]);
     * $consumerId = $result->consumerId;
     * ```
     *
     * @param array<string, mixed> $params consumer fields (`name`, `address`,
     *                                     `email`, `bankAccountNumber`, `bankRoutingNumber`, ...)
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function create(array $params, RequestOptions|array|null $options = null): ConsumerResult
    {
        $response = $this->transport->request(
            'POST',
            '/consumers',
            jsonBody: $this->normalizeParams($params),
            options: RequestOptions::from($options),
        );

        return ConsumerResult::fromArray($response->data);
    }

    /**
     * Retrieves a consumer by id.
     *
     * @param string|null $merchantNumber aggregators only: child merchant to act on behalf of
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function retrieve(
        string $consumerId,
        ?string $merchantNumber = null,
        RequestOptions|array|null $options = null,
    ): Consumer {
        $response = $this->transport->request(
            'GET',
            '/consumers/'.$this->encodeId($consumerId),
            query: ['merchantNumber' => $merchantNumber],
            options: RequestOptions::from($options),
        );

        return Consumer::fromArray($response->data);
    }

    /**
     * Updates a consumer.
     *
     * @param array<string, mixed> $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function update(
        string $consumerId,
        array $params,
        RequestOptions|array|null $options = null,
    ): ConsumerResult {
        $response = $this->transport->request(
            'PUT',
            '/consumers/'.$this->encodeId($consumerId),
            jsonBody: $this->normalizeParams($params),
            options: RequestOptions::from($options),
        );

        return ConsumerResult::fromArray($response->data);
    }

    /**
     * Lists consumers, optionally filtered.
     *
     * Supported filters: `name`, `address1`, `address2`, `city`, `state`,
     * `page`, `pageSize`, `sortBy`, `sortDirection` ({@see SortDirection}),
     * `merchantNumber`.
     *
     * ```php
     * foreach ($client->consumers->list(['city' => 'Austin'])->autoPagingIterator() as $consumer) {
     *     // ...
     * }
     * ```
     *
     * @param array<string, mixed> $filters
     * @param RequestOptions|array<string, mixed>|null $options
     *
     * @return PaginatedList<Consumer>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): PaginatedList
    {
        $requestOptions = RequestOptions::from($options);

        $response = $this->transport->request(
            'GET',
            '/consumers',
            query: $filters,
            options: $requestOptions,
        );

        $payload = $response->data['consumers'] ?? [];
        $payload = \is_array($payload) ? $payload : [];

        $items = [];
        foreach ((array) ($payload['results'] ?? []) as $consumer) {
            if (\is_array($consumer)) {
                $items[] = Consumer::fromArray($consumer);
            }
        }

        $pagination = \is_array($payload['pagination'] ?? null)
            ? Pagination::fromArray($payload['pagination'])
            : null;

        return new PaginatedList(
            items: $items,
            pagination: $pagination,
            pageFetcher: fn (int $page): PaginatedList => $this->list(
                ['page' => $page] + $filters,
                $requestOptions,
            ),
        );
    }

    private function encodeId(string $consumerId): string
    {
        if ('' === trim($consumerId)) {
            throw new InvalidArgumentException('A consumer id is required.');
        }

        return rawurlencode($consumerId);
    }
}
