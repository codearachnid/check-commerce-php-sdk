<?php

declare(strict_types=1);

namespace CheckCommerce\Auth;

/**
 * Keeps tokens in memory for the lifetime of the process.
 */
final class InMemoryTokenStore implements TokenStoreInterface
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function get(string $key): ?AccessToken
    {
        return $this->tokens[$key] ?? null;
    }

    public function put(string $key, AccessToken $token): void
    {
        $this->tokens[$key] = $token;
    }

    public function forget(string $key): void
    {
        unset($this->tokens[$key]);
    }
}
