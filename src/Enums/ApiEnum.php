<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Lenient parsing for API enum values.
 *
 * The API serializes enums either as their string name or their ordinal
 * position, so both forms are accepted. Unknown values map to null rather
 * than throwing, keeping the SDK forward-compatible with new API values.
 */
trait ApiEnum
{
    public static function fromApi(self|string|int|null $value): ?self
    {
        if (null === $value || $value instanceof self) {
            return $value;
        }

        if (\is_int($value)) {
            return self::cases()[$value] ?? null;
        }

        if (null !== $exact = self::tryFrom($value)) {
            return $exact;
        }

        foreach (self::cases() as $case) {
            if (0 === strcasecmp($case->value, $value)) {
                return $case;
            }
        }

        return null;
    }
}
