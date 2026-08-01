<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * A consumer's postal address.
 */
final class Address extends ApiResource
{
    private function __construct(
        public readonly ?string $address1,
        public readonly ?string $address2,
        public readonly ?string $city,
        public readonly ?string $stateProvince,
        public readonly ?string $zip,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $address = new self(
            address1: self::stringValue($data, 'address1'),
            address2: self::stringValue($data, 'address2'),
            city: self::stringValue($data, 'city'),
            stateProvince: self::stringValue($data, 'stateProvince'),
            zip: self::stringValue($data, 'zip'),
        );
        $address->raw = $data;

        return $address;
    }
}
