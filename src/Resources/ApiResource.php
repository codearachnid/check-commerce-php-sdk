<?php

declare(strict_types=1);

namespace CheckCommerce\Resources;

/**
 * Base class for typed API response objects.
 *
 * Typed properties cover the documented fields; the full decoded payload is
 * always available through {@see toArray()} and array access, so fields added
 * by the API later remain reachable without an SDK update.
 *
 * @implements \ArrayAccess<string, mixed>
 */
abstract class ApiResource implements \ArrayAccess, \JsonSerializable
{
    /** @var array<string, mixed> */
    protected array $raw = [];

    /**
     * The full decoded response payload, including fields without a typed property.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->raw[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException(static::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException(static::class.' is immutable.');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function stringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function intValue(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function floatValue(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function boolValue(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function scalarAsString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_scalar($value) && '' !== $value ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function dateValue(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    protected static function stringList(array $data, string $key): array
    {
        $values = $data[$key] ?? null;

        if (!\is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    protected static function arrayValue(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : null;
    }
}
