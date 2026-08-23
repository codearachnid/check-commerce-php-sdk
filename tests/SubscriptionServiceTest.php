<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Enums\SubscriptionEndCode;
use CheckCommerce\Enums\SubscriptionStatus;
use CheckCommerce\Enums\TransactionType;

final class SubscriptionServiceTest extends TestCase
{
    public function testCreatesSubscription(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(201, [
            'subscriptionId' => 'sub-0001',
            'consumerId' => 'con-0001',
        ]);

        $result = $client->subscriptions->create([
            'startTime' => new \DateTimeImmutable('2026-09-01T00:00:00Z'),
            'amount' => 25.00,
            'schCode' => 'Monthly:1',
            'endCode' => SubscriptionEndCode::Indefinite,
            'transactionType' => TransactionType::Debit,
            'status' => SubscriptionStatus::Active,
            'consumerInfo' => ['consumerId' => 'con-0001'],
        ]);

        self::assertSame('sub-0001', $result->subscriptionId);
        self::assertSame('con-0001', $result->consumerId);

        $body = $this->decodeBody($this->http->lastRequest());
        self::assertSame('Indefinite', $body['endCode']);
        self::assertSame('Active', $body['status']);
        self::assertSame('Debit', $body['transactionType']);
    }

    public function testRetrievesSubscription(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'id' => 'sub-0001',
            'amount' => 25.0,
            'schCode' => 'Monthly:1',
            'endCode' => 'EndDate',
            'endTime' => '2027-01-01T00:00:00Z',
            'status' => 'Suspended',
            'transactionType' => 'Debit',
            'consumerInfo' => ['consumerId' => 'con-0001', 'name' => 'Jane Doe'],
        ]);

        $subscription = $client->subscriptions->retrieve('sub-0001');

        self::assertSame('sub-0001', $subscription->id);
        self::assertSame(SubscriptionEndCode::EndDate, $subscription->endCode);
        self::assertSame(SubscriptionStatus::Suspended, $subscription->status);
        self::assertSame('Monthly:1', $subscription->scheduleCode);
        self::assertSame('Jane Doe', $subscription->consumerInfo?->name);
    }

    public function testUpdatesSubscription(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['subscriptionId' => 'sub-0001']);

        $client->subscriptions->update('sub-0001', ['amount' => 30.0]);

        $request = $this->http->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(
            'https://sandbox.checkcommerce.com/api/transaction/subscription/sub-0001',
            (string) $request->getUri(),
        );
    }

    public function testListsSubscriptions(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'subscriptions' => [
                'pagination' => ['currentPage' => 1, 'pageSize' => 20, 'totalPages' => 1, 'totalRecords' => 1],
                'results' => [
                    ['id' => 'sub-0001', 'status' => 'Active'],
                ],
            ],
        ]);

        $page = $client->subscriptions->list(['includeSuspended' => true]);

        self::assertCount(1, $page->items);
        self::assertFalse($page->hasMorePages());
        self::assertSame(SubscriptionStatus::Active, $page->items[0]->status);
        self::assertStringContainsString('includeSuspended=true', (string) $this->http->lastRequest()->getUri());
    }
}
