<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Enums\BatchStatus;
use CheckCommerce\Enums\FileDelimiter;
use CheckCommerce\Enums\TransactionType;
use CheckCommerce\Exception\InvalidArgumentException;

final class BatchServiceTest extends TestCase
{
    public function testSubmitsBatch(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'batchId' => 9001,
            'batchStatus' => 'Pending',
            'totalAmount' => 52.5,
            'transactionCount' => 2,
        ]);

        $result = $client->batches->submit([
            ['merchantNumber' => '999997', 'transactionType' => TransactionType::Debit, 'amount' => 42.5],
            ['merchantNumber' => '999997', 'transactionType' => TransactionType::Debit, 'amount' => 10.0],
        ]);

        self::assertSame(9001, $result->batchId);
        self::assertSame(BatchStatus::Pending, $result->status);
        self::assertSame(2, $result->transactionCount);

        $body = $this->decodeBody($this->http->lastRequest());
        self::assertFalse($body['isAuthFile']);
        self::assertCount(2, $body['transactions']);
        self::assertSame('Debit', $body['transactions'][0]['transactionType']);
    }

    public function testRejectsEmptyBatch(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);

        $client->batches->submit([]);
    }

    public function testFetchesBatchStatus(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['batchId' => 9001, 'batchStatus' => 'Processed']);

        $result = $client->batches->status(9001);

        self::assertSame(BatchStatus::Processed, $result->status);
        self::assertSame(
            'https://sandbox.checkcommerce.com/api/transaction/batch?batchId=9001',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    public function testUploadsBatchFileAsMultipart(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['batchId' => 9002, 'batchStatus' => 'Pending']);

        $result = $client->batches->upload(
            "999997,Debit,42.50\n",
            'batch.csv',
            FileDelimiter::Comma,
            isAuthFile: true,
        );

        self::assertSame(9002, $result->batchId);

        $request = $this->http->lastRequest();
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));

        $request->getBody()->rewind();
        $body = (string) $request->getBody();
        self::assertStringContainsString('name="file"; filename="batch.csv"', $body);
        self::assertStringContainsString("999997,Debit,42.50\n", $body);
        self::assertStringContainsString("name=\"delimiter\"\r\n\r\nComma", $body);
        self::assertStringContainsString("name=\"isAuthFile\"\r\n\r\ntrue", $body);
    }

    public function testRejectsEmptyUpload(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);

        $client->batches->upload('', 'batch.csv');
    }

    public function testRejectsMissingUploadFile(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);

        $client->batches->uploadFile('/nonexistent/batch.csv');
    }
}
