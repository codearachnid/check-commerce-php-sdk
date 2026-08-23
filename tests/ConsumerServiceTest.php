<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Exception\InvalidArgumentException;
use CheckCommerce\Resources\Consumer;

final class ConsumerServiceTest extends TestCase
{
    public function testCreatesConsumer(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(201, ['consumerId' => 'e7a4c3f2-0000-0000-0000-000000000001']);

        $result = $client->consumers->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'bankAccountNumber' => '1234567890',
            'bankRoutingNumber' => 121000248,
        ]);

        self::assertSame('e7a4c3f2-0000-0000-0000-000000000001', $result->consumerId);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://sandbox.checkcommerce.com/api/consumers', (string) $request->getUri());
        self::assertSame('Jane Doe', $this->decodeBody($request)['name']);
    }

    public function testRetrievesConsumerIntoTypedResource(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'consumerId' => 'abc',
            'name' => 'Jane Doe',
            'address' => ['address1' => '1 Main St', 'city' => 'Austin', 'stateProvince' => 'TX', 'zip' => '78701'],
            'bankRoutingNumber' => 121000248,
            'isSavingsAccount' => false,
            'newFieldFromApi' => 'preserved',
        ]);

        $consumer = $client->consumers->retrieve('abc');

        self::assertSame('Jane Doe', $consumer->name);
        self::assertSame('Austin', $consumer->address?->city);
        self::assertSame('121000248', $consumer->bankRoutingNumber);
        self::assertFalse($consumer->isSavingsAccount);
        // Unmapped fields stay reachable.
        self::assertSame('preserved', $consumer['newFieldFromApi']);

        self::assertSame(
            'https://sandbox.checkcommerce.com/api/consumers/abc',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    public function testUpdatesConsumer(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['consumerId' => 'abc']);

        $result = $client->consumers->update('abc', ['name' => 'Jane Q Doe']);

        self::assertSame('abc', $result->consumerId);
        self::assertSame('PUT', $this->http->lastRequest()->getMethod());
    }

    public function testRejectsEmptyConsumerId(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);

        $client->consumers->retrieve('  ');
    }

    public function testListsConsumersWithPagination(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'consumers' => [
                'pagination' => ['currentPage' => 1, 'pageSize' => 2, 'totalPages' => 2, 'totalRecords' => 3],
                'results' => [
                    ['consumerId' => 'one', 'name' => 'A'],
                    ['consumerId' => 'two', 'name' => 'B'],
                ],
            ],
        ]);

        $page = $client->consumers->list(['city' => 'Austin']);

        self::assertCount(2, $page->items);
        self::assertTrue($page->hasMorePages());
        self::assertInstanceOf(Consumer::class, $page->items[0]);
        self::assertSame(3, $page->pagination?->totalRecords);
        self::assertStringContainsString('city=Austin', (string) $this->http->lastRequest()->getUri());
    }

    public function testAutoPagingIteratorWalksEveryPage(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'consumers' => [
                'pagination' => ['currentPage' => 1, 'pageSize' => 2, 'totalPages' => 2, 'totalRecords' => 3],
                'results' => [
                    ['consumerId' => 'one'],
                    ['consumerId' => 'two'],
                ],
            ],
        ]);
        $this->http->queueJson(200, [
            'consumers' => [
                'pagination' => ['currentPage' => 2, 'pageSize' => 2, 'totalPages' => 2, 'totalRecords' => 3],
                'results' => [
                    ['consumerId' => 'three'],
                ],
            ],
        ]);

        $ids = [];
        foreach ($client->consumers->list()->autoPagingIterator() as $consumer) {
            $ids[] = $consumer->consumerId;
        }

        self::assertSame(['one', 'two', 'three'], $ids);
        self::assertStringContainsString('page=2', (string) $this->http->lastRequest()->getUri());
    }
}
