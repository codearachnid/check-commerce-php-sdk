<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown when the request never produced an API response — DNS failures,
 * connection timeouts, TLS errors, or an unreadable response body.
 */
class TransportException extends \RuntimeException implements CheckCommerceException
{
}
