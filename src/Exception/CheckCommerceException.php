<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Marker interface implemented by every exception thrown by this SDK.
 *
 * Catch this to handle any SDK failure with a single catch block.
 */
interface CheckCommerceException extends \Throwable
{
}
