<?php

declare(strict_types=1);

namespace CheckCommerce\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that replays queued responses and records every request.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|\Throwable> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    public function queue(ResponseInterface|\Throwable $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function queueJson(int $status, array $body = [], array $headers = []): void
    {
        $this->queue(new Response(
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            json_encode($body, \JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * Queues a successful authentication response.
     */
    public function queueAuthToken(string $token = 'test-jwt-token', string $expiresIn = '+1 hour'): void
    {
        $this->queueJson(200, [
            'tokenId' => 'c0a80121-7ac0-4e1c-9a0f-0a1b2c3d4e5f',
            'token' => $token,
            'expiresAtUTC' => (new \DateTimeImmutable($expiresIn, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ([] === $this->queue) {
            throw new \LogicException(\sprintf(
                'No response queued for %s %s.',
                $request->getMethod(),
                (string) $request->getUri(),
            ));
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        if ([] === $this->requests) {
            throw new \LogicException('No request has been sent.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    public function requestCount(): int
    {
        return \count($this->requests);
    }
}
