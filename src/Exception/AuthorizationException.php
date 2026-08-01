<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown on HTTP 403 — the token is valid but lacks the scope required by the
 * endpoint, or the feature is not enabled for the merchant account.
 */
final class AuthorizationException extends ApiException
{
}
