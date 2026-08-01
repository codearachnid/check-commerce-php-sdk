<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * A generated hosted payment page link.
 */
final class HostedPageLink extends ApiResource
{
    private function __construct(
        public readonly ?string $url,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $link = new self(
            url: self::stringValue($data, 'url'),
        );
        $link->raw = $data;

        return $link;
    }
}
