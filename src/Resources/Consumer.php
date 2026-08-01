<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * A stored consumer profile, including bank account details.
 */
final class Consumer extends ApiResource
{
    private function __construct(
        public readonly ?string $consumerId,
        public readonly ?string $name,
        public readonly ?Address $address,
        public readonly ?string $phoneNumber,
        public readonly ?\DateTimeImmutable $birthDay,
        public readonly ?string $email,
        public readonly ?string $ssn,
        public readonly ?string $driversLicenseNumber,
        public readonly ?string $driversLicenseState,
        public readonly ?string $bankAccountNumber,
        public readonly ?string $bankRoutingNumber,
        public readonly ?bool $isSavingsAccount,
        public readonly ?string $secCode,
        public readonly ?string $notes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $addressData = self::arrayValue($data, 'address');

        $consumer = new self(
            consumerId: self::stringValue($data, 'consumerId'),
            name: self::stringValue($data, 'name'),
            address: null !== $addressData ? Address::fromArray($addressData) : null,
            phoneNumber: self::stringValue($data, 'phoneNumber'),
            birthDay: self::dateValue($data, 'birthDay'),
            email: self::stringValue($data, 'email'),
            ssn: self::stringValue($data, 'ssn'),
            driversLicenseNumber: self::stringValue($data, 'dln'),
            driversLicenseState: self::stringValue($data, 'dls'),
            bankAccountNumber: self::stringValue($data, 'bankAccountNumber'),
            bankRoutingNumber: self::scalarAsString($data, 'bankRoutingNumber'),
            isSavingsAccount: self::boolValue($data, 'isSavingsAccount'),
            secCode: self::stringValue($data, 'secCode'),
            notes: self::stringValue($data, 'notes'),
        );
        $consumer->raw = $data;

        return $consumer;
    }
}
