<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Exception\AuthenticationException;
use CheckCommerce\Scope;

final class AuthenticationTest extends TestCase
{
    public function testAuthenticatesLazilyBeforeFirstRequest(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken('jwt-abc');
        $this->http->queueJson(200, ['transactionId' => 1, 'status' => 'Processed']);

        $client->transactions->status(transactionId: 1);

        self::assertSame(2, $this->http->requestCount());

        $authRequest = $this->http->requests[0];
        self::assertSame('POST', $authRequest->getMethod());
        self::assertSame('https://sandbox.checkcommerce.com/api/authenticate', (string) $authRequest->getUri());
        self::assertSame(
            ['apiKey' => 'test-api-key', 'merchantNumber' => '999997'],
            $this->decodeBody($authRequest),
        );

        $apiRequest = $this->http->requests[1];
        self::assertSame('Bearer jwt-abc', $apiRequest->getHeaderLine('Authorization'));
    }

    public function testReusesCachedTokenAcrossRequests(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken();
        $this->http->queueJson(200, ['status' => 'Processed']);
        $this->http->queueJson(200, ['status' => 'Processed']);

        $client->transactions->status(transactionId: 1);
        $client->transactions->status(transactionId: 2);

        // One authentication, two API calls.
        self::assertSame(3, $this->http->requestCount());
    }

    public function testRefreshesExpiredToken(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken('stale', '+10 seconds'); // inside the 60s expiry margin
        $this->http->queueJson(200, ['status' => 'Processed']);
        $this->http->queueAuthToken('fresh');
        $this->http->queueJson(200, ['status' => 'Processed']);

        $client->transactions->status(transactionId: 1);
        $client->transactions->status(transactionId: 2);

        self::assertSame(4, $this->http->requestCount());
        self::assertSame('Bearer fresh', $this->http->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testReauthenticatesOnceWhenTokenIsRejected(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken('revoked');
        $this->http->queueJson(401, ['title' => 'Unauthorized']);
        $this->http->queueAuthToken('fresh');
        $this->http->queueJson(200, ['status' => 'Processed']);

        $result = $client->transactions->status(transactionId: 1);

        self::assertSame('Processed', $result->statusRaw);
        self::assertSame('Bearer fresh', $this->http->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testThrowsWhenReauthenticationAlsoFails(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken('revoked');
        $this->http->queueJson(401, ['title' => 'Unauthorized']);
        $this->http->queueJson(401, ['title' => 'Invalid API key']);

        $this->expectException(AuthenticationException::class);

        $client->transactions->status(transactionId: 1);
    }

    public function testSendsRequestedScopes(): void
    {
        $client = $this->client(['scopes' => [Scope::Transactions, 'obp.hp']]);
        $this->http->queueAuthToken();
        $this->http->queueJson(200, []);

        $client->transactions->status(transactionId: 1);

        self::assertSame(
            ['obp.tran', 'obp.hp'],
            $this->decodeBody($this->http->requests[0])['scopes'],
        );
    }

    public function testExplicitAuthenticateReturnsToken(): void
    {
        $client = $this->client();
        $this->http->queueAuthToken('jwt-explicit');

        $token = $client->authenticate();

        self::assertSame('jwt-explicit', $token->token);
        self::assertNotNull($token->expiresAt);
        self::assertFalse($token->isExpired(0));
    }

    public function testFailsLoudlyWhenNoTokenIsReturned(): void
    {
        $client = $this->client();
        $this->http->queueJson(200, ['tokenId' => 'abc']); // no token field

        $this->expectException(AuthenticationException::class);

        $client->authenticate();
    }
}
