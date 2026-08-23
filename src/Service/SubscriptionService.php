<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\PaginatedList;
use CheckCommerce\Resources\Subscription;
use CheckCommerce\Resources\SubscriptionResult;

/**
 * Manage recurring payment subscriptions.
 */
final class SubscriptionService extends AbstractService
{
    /**
     * Creates a subscription.
     *
     * ```php
     * $result = $client->subscriptions->create([
     *     'startTime' => new DateTimeImmutable('next monday'),
     *     'amount' => 25.00,
     *     'schCode' => 'Monthly:1',
     *     'endCode' => SubscriptionEndCode::Indefinite,
     *     'transactionType' => TransactionType::Debit,
     *     'status' => SubscriptionStatus::Active,
     *     'consumerInfo' => ['consumerId' => $consumerId],
     * ]);
     * ```
     *
     * @param array<string, mixed> $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function create(array $params, RequestOptions|array|null $options = null): SubscriptionResult
    {
        $response = $this->transport->request(
            'POST',
            '/transaction/subscription',
            jsonBody: $this->normalizeParams($params),
            options: RequestOptions::from($options),
        );

        return SubscriptionResult::fromArray($response->data);
    }

    /**
     * Updates a subscription.
     *
     * @param array<string, mixed> $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function update(
        string $subscriptionId,
        array $params,
        RequestOptions|array|null $options = null,
    ): SubscriptionResult {
        $response = $this->transport->request(
            'PUT',
            '/transaction/subscription/'.$this->encodeId($subscriptionId, 'subscription id'),
            jsonBody: $this->normalizeParams($params),
            options: RequestOptions::from($options),
        );

        return SubscriptionResult::fromArray($response->data);
    }

    /**
     * Retrieves a subscription by id.
     *
     * @param string|null $merchantNumber aggregators only: child merchant to act on behalf of
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function retrieve(
        string $subscriptionId,
        ?string $merchantNumber = null,
        RequestOptions|array|null $options = null,
    ): Subscription {
        $response = $this->transport->request(
            'GET',
            '/transaction/subscription/'.$this->encodeId($subscriptionId, 'subscription id'),
            query: ['merchantNumber' => $merchantNumber],
            options: RequestOptions::from($options),
        );

        return Subscription::fromArray($response->data);
    }

    /**
     * Lists subscriptions, optionally filtered.
     *
     * Supported filters: `group`, `consumerId`, `includeSuspended`, `page`,
     * `pageSize`, `sortBy`, `sortDirection`, `merchantNumber`.
     *
     * @param array<string, mixed> $filters
     * @param RequestOptions|array<string, mixed>|null $options
     *
     * @return PaginatedList<Subscription>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): PaginatedList
    {
        return $this->paginate('/transaction/subscriptions', 'subscriptions', Subscription::fromArray(...), $filters, RequestOptions::from($options));
    }
}
