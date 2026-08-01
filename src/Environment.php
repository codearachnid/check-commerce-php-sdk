<?php

declare(strict_types=1);

namespace CheckCommerce;

/**
 * Check Commerce API environments and their base URLs.
 */
enum Environment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://checkcommerce.com/api',
            self::Sandbox => 'https://sandbox.checkcommerce.com/api',
        };
    }
}
