<?php

declare(strict_types=1);

namespace CheckCommerce\Auth;

/**
 * A bearer token issued by the `/authenticate` endpoint.
 */
final class AccessToken implements \JsonSerializable
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $token,
        public readonly ?string $tokenId = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data decoded `/authenticate` response body
     */
    public static function fromArray(array $data): self
    {
        $expiresAt = null;
        if (\is_string($data['expiresAtUTC'] ?? null) && '' !== $data['expiresAtUTC']) {
            try {
                $expiresAt = new \DateTimeImmutable($data['expiresAtUTC']);
            } catch (\Exception) {
                $expiresAt = null;
            }
        }

        return new self(
            token: \is_string($data['token'] ?? null) ? $data['token'] : '',
            tokenId: \is_string($data['tokenId'] ?? null) ? $data['tokenId'] : null,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Whether the token is expired, or will expire within the given margin.
     */
    public function isExpired(int $marginSeconds = 0, ?\DateTimeImmutable $now = null): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now->getTimestamp() + $marginSeconds >= $this->expiresAt->getTimestamp();
    }

    /**
     * @return array{token: string, tokenId: ?string, expiresAtUTC: ?string}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'tokenId' => $this->tokenId,
            'expiresAtUTC' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{token: string, tokenId: ?string, expiresAtUTC: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
