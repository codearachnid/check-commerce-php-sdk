<?php

declare(strict_types=1);

namespace CheckCommerce\Exception;

/**
 * Thrown on HTTP 400/422 — the request was rejected by validation.
 *
 * Inspect {@see ApiException::$validationErrors} for per-field details.
 */
final class ValidationException extends ApiException
{
}
