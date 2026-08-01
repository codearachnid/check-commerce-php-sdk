<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\CheckCommerceClient;
use CheckCommerce\Exception\ApiException;
use CheckCommerce\Exception\AuthorizationException;
use CheckCommerce\Exception\NotFoundException;
use CheckCommerce\Exception\RateLimitException;
use CheckCommerce\Exception\ServerException;
use CheckCommerce\Exception\TransportException;
use CheckCommerce\Exception\ValidationException;
use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Tests\Support\FakeNetworkException;
use GuzzleHttp\Psr7\Request;

final class HttpTransportTest extends TestCase
{
    public function testSendsVersionCorrelationAndUserAgentHeaders(): void
    {
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, []);

        $transport->request('POST', '/transaction', jsonBody: ['a' => 1], options: new RequestOptions(
            correlationId: 'corr-123',
            headers: ['X-Custom' => 'yes'],
        ));

        $request = $this->http->lastRequest();
        self::assertSame('1.0', $request->getHeaderLine('api-version'));
        self::assertSame('application/json; ver=1.0', $request->getHeaderLine('Content-Type'));
        self::assertSame('corr-123', $request->getHeaderLine('X-Correlation-ID'));
        self::assertSame('yes', $request->getHeaderLine('X-Custom'));
        self::assertStringContainsString('check-commerce-php/'.CheckCommerceClient::VERSION, $request->getHeaderLine('User-Agent'));
    }

    public function testBuildsQueryStringsFromMixedTypes(): void
    {
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, []);

        $transport->request('GET', '/consumers', query: [
            'name' => 'Jane Doe',
            'includeSuspended' => true,
            'page' => 2,
            'merchantNumber' => null,
        ]);

        self::assertSame(
            'https://sandbox.checkcommerce.com/api/consumers?name=Jane%20Doe&includeSuspended=true&page=2',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    public function testMapsErrorStatusesToTypedExceptions(): void
    {
        foreach ([
            403 => AuthorizationException::class,
            404 => NotFoundException::class,
            422 => ValidationException::class,
            409 => ApiException::class,
        ] as $status => $expected) {
            $transport = $this->transport(['max_retries' => 0]);
            $this->http->queueAuthToken();
            $this->http->queueJson($status, ['title' => 'Problem']);

            try {
                $transport->request('GET', '/consumers');
                self::fail("Expected exception for HTTP {$status}");
            } catch (ApiException $exception) {
                self::assertInstanceOf($expected, $exception);
                self::assertSame($status, $exception->getStatusCode());
            }
        }
    }

    public function testExposesErrorDetailsFromResponseBody(): void
    {
        $transport = $this->transport(['max_retries' => 0]);
        $this->http->queueAuthToken();
        $this->http->queueJson(422, [
            'correlationId' => 'abc-123',
            'title' => 'Validation failed',
            'status' => 422,
            'code' => 'VAL-001',
            'detail' => 'The request is invalid',
            'errors' => [
                ['property' => 'amount', 'code' => 'VAL-002', 'detail' => 'Amount must be positive'],
            ],
        ]);

        try {
            $transport->request('POST', '/transaction', jsonBody: []);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('VAL-001', $exception->getErrorCode());
            self::assertSame('abc-123', $exception->getCorrelationId());
            self::assertSame('Validation failed', $exception->getTitle());
            self::assertCount(1, $exception->getValidationErrors());
            self::assertSame('amount', $exception->getValidationErrors()[0]->property);
            self::assertStringContainsString('Validation failed', $exception->getMessage());
            self::assertStringContainsString('VAL-001', $exception->getMessage());
        }
    }

    public function testRetriesRateLimitedRequests(): void
    {
        $delays = [];
        $transport = $this->transport();
        $transport->setSleeper(function (int $ms) use (&$delays): void {
            $delays[] = $ms;
        });

        $this->http->queueAuthToken();
        $this->http->queueJson(429, [], ['Retry-After' => '1']);
        $this->http->queueJson(200, ['ok' => true]);

        $response = $transport->request('POST', '/transaction', jsonBody: []);

        self::assertSame(['ok' => true], $response->data);
        self::assertSame([1000], $delays); // honored Retry-After
    }

    public function testRetriesServerErrorsOnGetOnly(): void
    {
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queueJson(503, []);
        $this->http->queueJson(200, ['ok' => true]);

        $response = $transport->request('GET', '/transaction/status');
        self::assertSame(['ok' => true], $response->data);

        // POST must not be retried on 5xx: the write may have gone through.
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queueJson(503, []);

        $this->expectException(ServerException::class);
        $transport->request('POST', '/transaction', jsonBody: []);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $transport = $this->transport(['max_retries' => 2]);
        $this->http->queueAuthToken();
        $this->http->queueJson(429, []);
        $this->http->queueJson(429, []);
        $this->http->queueJson(429, []);

        try {
            $transport->request('GET', '/consumers');
            self::fail('Expected RateLimitException');
        } catch (RateLimitException) {
            self::assertSame(4, $this->http->requestCount()); // auth + 3 attempts
        }
    }

    public function testRetriesNetworkFailuresForReads(): void
    {
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queue(new FakeNetworkException(new Request('GET', 'https://example.test')));
        $this->http->queueJson(200, ['ok' => true]);

        $response = $transport->request('GET', '/consumers');

        self::assertSame(['ok' => true], $response->data);
    }

    public function testWrapsNetworkFailuresForWrites(): void
    {
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queue(new FakeNetworkException(new Request('POST', 'https://example.test')));

        $this->expectException(TransportException::class);

        $transport->request('POST', '/transaction', jsonBody: []);
    }

    public function testThrowsTransportExceptionOnInvalidJson(): void
    {
        $transport = $this->transport();
        $this->http->queueAuthToken();
        $this->http->queue(new \GuzzleHttp\Psr7\Response(200, [], 'not json'));

        $this->expectException(TransportException::class);

        $transport->request('GET', '/consumers');
    }
}
