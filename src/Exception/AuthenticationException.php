<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown on HTTP 401 — the API key, merchant number or bearer token was rejected.
 */
final class AuthenticationException extends ApiException
{
}
