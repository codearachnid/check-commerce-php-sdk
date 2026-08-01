<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Http\HttpTransport;

/**
 * Base class for API services.
 *
 * @internal
 */
abstract class AbstractService
{
    public function __construct(
        protected readonly HttpTransport $transport,
    ) {
    }

    /**
     * Recursively converts request parameters to JSON-ready values: backed
     * enums become their value, date objects become ISO 8601 strings.
     *
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    protected function normalizeParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            $normalized[$key] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
                $value instanceof \JsonSerializable => $value->jsonSerialize(),
                \is_array($value) => $this->normalizeParams($value),
                default => $value,
            };
        }

        return $normalized;
    }
}
