<?php

declare(strict_types=1);

namespace CheckCommerce\Http;

use CheckCommerce\Auth\Authenticator;
use CheckCommerce\CheckCommerceClient;
use CheckCommerce\Configuration;
use CheckCommerce\Exception\ApiException;
use CheckCommerce\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends API requests over any PSR-18 HTTP client.
 *
 * Responsibilities: URL and header construction, bearer token injection,
 * automatic re-authentication on 401, safe retries with exponential backoff,
 * JSON decoding and error mapping.
 *
 * @internal used by the services; not part of the public API surface
 */
final class HttpTransport
{
    private const RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 504];

    private ?Authenticator $authenticator = null;

    /** @var \Closure(int): void */
    private \Closure $sleeper;

    public function __construct(
        private readonly Configuration $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        $this->sleeper = static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    public function setAuthenticator(Authenticator $authenticator): void
    {
        $this->authenticator = $authenticator;
    }

    /**
     * Replaces the delay function used between retries. Used by tests.
     *
     * @param \Closure(int): void $sleeper receives the delay in milliseconds
     */
    public function setSleeper(\Closure $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    /**
     * Sends a JSON request and returns the decoded successful response.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $jsonBody
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $jsonBody = null,
        ?RequestOptions $options = null,
        bool $authenticated = true,
    ): ApiResponse {
        $options ??= new RequestOptions();

        return $this->send($method, $path, $query, $options, $authenticated, function () use ($jsonBody): array {
            if (null === $jsonBody) {
                return [null, null];
            }

            return [
                $this->encodeJson($jsonBody),
                'application/json; ver='.$this->config->apiVersion,
            ];
        });
    }

    /**
     * Sends a multipart/form-data request, e.g. for batch file uploads.
     *
     * @param array<string, string> $fields simple form fields
     * @param array<string, array{filename: string, contents: string, contentType?: string}> $files
     */
    public function upload(
        string $path,
        array $fields = [],
        array $files = [],
        ?RequestOptions $options = null,
    ): ApiResponse {
        $options ??= new RequestOptions();
        $boundary = 'CheckCommerceBoundary'.bin2hex(random_bytes(16));

        return $this->send('POST', $path, [], $options, true, function () use ($fields, $files, $boundary): array {
            return [
                $this->encodeMultipart($fields, $files, $boundary),
                'multipart/form-data; boundary='.$boundary,
            ];
        });
    }

    /**
     * @param array<string, mixed> $query
     * @param \Closure(): array{?string, ?string} $bodyFactory returns [body, contentType]
     */
    private function send(
        string $method,
        string $path,
        array $query,
        RequestOptions $options,
        bool $authenticated,
        \Closure $bodyFactory,
    ): ApiResponse {
        $attempt = 0;
        $reauthenticated = false;

        while (true) {
            $request = $this->buildRequest($method, $path, $query, $options, $authenticated, $bodyFactory);

            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (NetworkExceptionInterface $exception) {
                if ($this->canRetryNetworkFailure($method, $attempt)) {
                    ($this->sleeper)($this->backoffDelayMs($attempt));
                    ++$attempt;

                    continue;
                }

                throw new TransportException(
                    \sprintf('Request to %s failed: %s', $path, $exception->getMessage()),
                    previous: $exception,
                );
            } catch (ClientExceptionInterface $exception) {
                throw new TransportException(
                    \sprintf('Request to %s could not be sent: %s', $path, $exception->getMessage()),
                    previous: $exception,
                );
            }

            $statusCode = $response->getStatusCode();
            $headers = $this->normalizeHeaders($response);
            $rawBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                return new ApiResponse($statusCode, $headers, $this->decodeJson($rawBody, $path));
            }

            if (401 === $statusCode && $authenticated && !$reauthenticated && null !== $this->authenticator) {
                $this->authenticator->invalidate();
                $reauthenticated = true;

                continue;
            }

            if ($this->canRetryStatus($method, $statusCode, $attempt)) {
                ($this->sleeper)($this->backoffDelayMs($attempt, $headers['retry-after'] ?? null));
                ++$attempt;

                continue;
            }

            throw ApiException::fromResponse($statusCode, $this->decodeJsonLenient($rawBody), $headers);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param \Closure(): array{?string, ?string} $bodyFactory
     */
    private function buildRequest(
        string $method,
        string $path,
        array $query,
        RequestOptions $options,
        bool $authenticated,
        \Closure $bodyFactory,
    ): RequestInterface {
        $request = $this->requestFactory->createRequest(
            $method,
            $this->buildUri($path, [...$query, ...$options->query]),
        );

        $request = $request
            ->withHeader('Accept', 'application/json')
            ->withHeader('api-version', $this->config->apiVersion)
            ->withHeader('User-Agent', \sprintf(
                'check-commerce-php/%s php/%s',
                CheckCommerceClient::VERSION,
                \PHP_VERSION,
            ));

        if ($authenticated) {
            if (null === $this->authenticator) {
                throw new TransportException('No authenticator configured for an authenticated request.');
            }

            $request = $request->withHeader('Authorization', 'Bearer '.$this->authenticator->token()->token);
        }

        if (null !== $options->correlationId) {
            $request = $request->withHeader('X-Correlation-ID', $options->correlationId);
        }

        foreach ($options->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        [$body, $contentType] = $bodyFactory();

        if (null !== $body) {
            $request = $request->withBody($this->streamFactory->createStream($body));

            if (null !== $contentType) {
                $request = $request->withHeader('Content-Type', $contentType);
            }
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUri(string $path, array $query): string
    {
        $uri = $this->config->baseUrl.'/'.ltrim($path, '/');

        $normalized = [];
        foreach ($query as $name => $value) {
            if (null === $value) {
                continue;
            }

            $normalized[$name] = match (true) {
                \is_bool($value) => $value ? 'true' : 'false',
                $value instanceof \BackedEnum => (string) $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d\TH:i:s\Z'),
                default => (string) $value,
            };
        }

        if ([] !== $normalized) {
            $uri .= '?'.http_build_query($normalized, '', '&', \PHP_QUERY_RFC3986);
        }

        return $uri;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        try {
            return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            throw new TransportException('Unable to encode the request body as JSON: '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body, string $path): array
    {
        if ('' === trim($body)) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TransportException(
                \sprintf('The response from %s is not valid JSON: %s', $path, $exception->getMessage()),
                previous: $exception,
            );
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonLenient(string $body): array
    {
        if ('' === trim($body)) {
            return [];
        }

        $decoded = json_decode($body, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, array{filename: string, contents: string, contentType?: string}> $files
     */
    private function encodeMultipart(array $fields, array $files, string $boundary): string
    {
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= $value."\r\n";
        }

        foreach ($files as $name => $file) {
            $filename = str_replace('"', '', $file['filename']);
            $contentType = $file['contentType'] ?? 'application/octet-stream';

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: {$contentType}\r\n\r\n";
            $body .= $file['contents']."\r\n";
        }

        return $body."--{$boundary}--\r\n";
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        return $headers;
    }

    private function canRetryNetworkFailure(string $method, int $attempt): bool
    {
        // A dropped connection leaves the outcome of a write unknown, so only
        // reads are retried at the network level.
        return 'GET' === $method && $attempt < $this->config->maxRetries;
    }

    private function canRetryStatus(string $method, int $statusCode, int $attempt): bool
    {
        if ($attempt >= $this->config->maxRetries || !\in_array($statusCode, self::RETRYABLE_STATUS_CODES, true)) {
            return false;
        }

        // 429 means the request was rejected before processing, which is safe
        // to retry for any method. 5xx may have partially processed a write.
        return 429 === $statusCode || 'GET' === $method;
    }

    private function backoffDelayMs(int $attempt, ?string $retryAfter = null): int
    {
        if (null !== $retryAfter && is_numeric($retryAfter)) {
            return min((int) $retryAfter * 1000, $this->config->retryMaxDelayMs);
        }

        $ceiling = (int) min(
            $this->config->retryMaxDelayMs,
            $this->config->retryInitialDelayMs * (2 ** min($attempt, 30)),
        );

        // Full jitter between 50% and 100% of the ceiling avoids thundering herds.
        return random_int(intdiv($ceiling, 2), max(1, $ceiling));
    }
}
