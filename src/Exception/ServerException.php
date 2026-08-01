<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown on HTTP 5xx — the API failed to process an otherwise valid request.
 */
final class ServerException extends ApiException
{
}
