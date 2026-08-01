<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown when the SDK is used with invalid arguments, before any request is sent.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements CheckCommerceException
{
}
