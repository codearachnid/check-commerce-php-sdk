<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Enums\PaymentType;
use CheckCommerce\Enums\TransactionStatus;
use CheckCommerce\Enums\TransactionType;
use CheckCommerce\Exception\InvalidArgumentException;

final class TransactionServiceTest extends TestCase
{
    public function testCreateWrapsRequestWithPaymentType(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['transactionId' => 123456789, 'status' => 'Processed']);

        $result = $client->transactions->create([
            'merchantNumber' => '999997',
            'transactionType' => TransactionType::Debit,
            'amount' => 42.50,
            'originateDate' => new \DateTimeImmutable('2026-08-03T00:00:00Z'),
        ]);

        self::assertSame(123456789, $result->transactionId);
        self::assertSame(TransactionStatus::Processed, $result->status);

        $body = $this->decodeBody($this->http->lastRequest());
        self::assertSame('ACH', $body['paymentType']);
        self::assertSame('Debit', $body['request']['transactionType']);
        self::assertSame(42.50, $body['request']['amount']);
        self::assertSame('2026-08-03T00:00:00+00:00', $body['request']['originateDate']);
    }

    public function testConvenienceMethodsSetTransactionType(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();

        foreach (['debit' => 'Debit', 'credit' => 'Credit', 'refund' => 'Refund', 'void' => 'Void'] as $method => $type) {
            $this->http->queueJson(200, ['status' => 'Processed']);

            $client->transactions->{$method}(['merchantNumber' => '999997', 'amount' => 10.0]);

            self::assertSame($type, $this->decodeBody($this->http->lastRequest())['request']['transactionType']);
        }
    }

    public function testExplicitTransactionTypeWinsOverConvenienceDefault(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, []);

        $client->transactions->debit([
            'merchantNumber' => '999997',
            'transactionType' => TransactionType::PrenoteDebit,
        ]);

        self::assertSame('PrenoteDebit', $this->decodeBody($this->http->lastRequest())['request']['transactionType']);
    }

    public function testStatusQueriesByIdAndType(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['transactionId' => 42, 'status' => 'Originated']);

        $result = $client->transactions->status(
            transactionId: 42,
            requestType: PaymentType::Ach,
        );

        self::assertSame(TransactionStatus::Originated, $result->status);
        self::assertSame(
            'https://sandbox.checkcommerce.com/api/transaction/status?transactionId=42&requestType=ACH',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    public function testStatusRequiresAnIdentifier(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);

        $client->transactions->status();
    }

    public function testAuthStatusQueriesByReferenceNumber(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['status' => 'Authentication']);

        $result = $client->transactions->authStatus(referenceNumber: 'REF-9');

        self::assertSame(TransactionStatus::Authentication, $result->status);
        self::assertSame(
            'https://sandbox.checkcommerce.com/api/transaction/authstatus?referenceNumber=REF-9',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    public function testMapsDeclineWithProcessingFailure(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'correlationId' => 'abb8e7ef-d9f9-40b9-8215-6408712932cd',
            'transactionId' => 123456789,
            'status' => 'Declined',
            'notes' => ['Threshold Exceeded:Dollars Daily Max'],
            'processingFailure' => [
                'code' => 'PROC:VAL-003',
                'detail' => 'Threshold Exceeded',
                'validationErrors' => [
                    ['property' => 'Threshold', 'code' => 'VAL-1', 'detail' => 'Daily max'],
                ],
            ],
        ]);

        $result = $client->transactions->status(transactionId: 123456789);

        self::assertTrue($result->isDeclined());
        self::assertTrue($result->hasProcessingFailure());
        self::assertSame('PROC:VAL-003', $result->processingFailure?->code);
        self::assertSame('Threshold', $result->processingFailure?->validationErrors[0]->property);
        self::assertSame(['Threshold Exceeded:Dollars Daily Max'], $result->notes);
    }

    public function testMapsIntegerStatusOrdinals(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['status' => 0]); // ordinal for Processed

        $result = $client->transactions->status(transactionId: 1);

        self::assertSame(TransactionStatus::Processed, $result->status);
        self::assertSame(0, $result->statusRaw);
    }

    public function testUnknownStatusStaysAccessibleAsRaw(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['status' => 'BrandNewStatus']);

        $result = $client->transactions->status(transactionId: 1);

        self::assertNull($result->status);
        self::assertSame('BrandNewStatus', $result->statusRaw);
    }
}
