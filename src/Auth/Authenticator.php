<?php

declare(strict_types=1);

namespace CheckCommerce\Auth;

use CheckCommerce\Configuration;
use CheckCommerce\Exception\AuthenticationException;
use CheckCommerce\Http\HttpTransport;

/**
 * Acquires bearer tokens and keeps them fresh.
 *
 * Tokens are fetched lazily on the first authenticated request, cached in the
 * configured {@see TokenStoreInterface}, and refreshed automatically shortly
 * before they expire.
 *
 * @internal use {@see \CheckCommerce\CheckCommerceClient::authenticate()} instead
 */
final class Authenticator
{
    private readonly string $cacheKey;

    public function __construct(
        private readonly HttpTransport $transport,
        private readonly Configuration $config,
        private readonly TokenStoreInterface $store,
    ) {
        $this->cacheKey = 'check-commerce.token.'.hash('sha256', implode('|', [
            $this->config->baseUrl,
            $this->config->merchantNumber,
            hash('sha256', $this->config->apiKey),
            implode(',', $this->config->scopes),
        ]));
    }

    /**
     * Returns a valid token, authenticating when none is cached or the cached
     * token is within the configured expiry margin.
     */
    public function token(): AccessToken
    {
        $cached = $this->store->get($this->cacheKey);

        if (null !== $cached
            && '' !== $cached->token
            && !$cached->isExpired($this->config->tokenExpiryMarginSeconds)
        ) {
            return $cached;
        }

        return $this->refresh();
    }

    /**
     * Forces a new token to be issued, replacing any cached one.
     */
    public function refresh(): AccessToken
    {
        $body = [
            'apiKey' => $this->config->apiKey,
            'merchantNumber' => $this->config->merchantNumber,
        ];

        if ([] !== $this->config->scopes) {
            $body['scopes'] = $this->config->scopes;
        }

        $response = $this->transport->request('POST', '/authenticate', jsonBody: $body, authenticated: false);

        $token = AccessToken::fromArray($response->data);

        if ('' === $token->token) {
            throw new AuthenticationException(
                'Authentication succeeded but the response did not contain a token.',
                $response->statusCode,
                responseBody: $response->data,
                responseHeaders: $response->headers,
            );
        }

        $this->store->put($this->cacheKey, $token);

        return $token;
    }

    /**
     * Drops the cached token so the next request authenticates again.
     */
    public function invalidate(): void
    {
        $this->store->forget($this->cacheKey);
    }
}
