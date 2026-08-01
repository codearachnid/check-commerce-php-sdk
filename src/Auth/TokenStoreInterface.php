<?php

declare(strict_types=1);

namespace CheckCommerce\Auth;

/**
 * Storage for issued bearer tokens.
 *
 * The default {@see InMemoryTokenStore} keeps tokens for the lifetime of the
 * process. Provide your own implementation (backed by a cache, database, etc.)
 * to reuse tokens across processes and avoid re-authenticating on every request.
 */
interface TokenStoreInterface
{
    /**
     * Returns the stored token for the key, or null when absent.
     *
     * Expiry is checked by the caller; implementations may additionally evict
     * expired tokens on their own.
     */
    public function get(string $key): ?AccessToken;

    public function put(string $key, AccessToken $token): void;

    public function forget(string $key): void;
}
