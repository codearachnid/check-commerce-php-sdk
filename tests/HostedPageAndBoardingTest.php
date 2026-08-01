<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

final class HostedPageAndBoardingTest extends TestCase
{
    public function testCreatesHostedPageLink(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['url' => 'https://sandbox.checkcommerce.com/hp/abc123']);

        $link = $client->hostedPages->createLink([
            'customer' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
            'order' => ['total' => 99.95, 'returnURL' => 'https://example.com/thanks'],
        ]);

        self::assertSame('https://sandbox.checkcommerce.com/hp/abc123', $link->url);
        self::assertSame(
            'https://sandbox.checkcommerce.com/api/hostedpage/link',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    public function testBoardsMerchantsAndSurfacesFailures(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, [
            'correlationId' => 'corr-1',
            'boardedMerchants' => [
                ['companyName' => 'Good Co', 'merchantNumber' => '111111'],
            ],
            'boardingFailures' => [
                [
                    'companyName' => 'Bad Co',
                    'processingFailure' => ['code' => 'BRD-1', 'detail' => 'Missing bank data'],
                ],
            ],
        ]);

        $result = $client->boarding->board(['merchants' => [['companyName' => 'Good Co'], ['companyName' => 'Bad Co']]]);

        self::assertSame('corr-1', $result->correlationId);
        self::assertCount(1, $result->boardedMerchants);
        self::assertTrue($result->hasFailures());
        self::assertSame('Bad Co', $result->boardingFailures[0]->companyName);
        self::assertSame('BRD-1', $result->boardingFailures[0]->processingFailure?->code);
    }
}
