<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * Too many requests — slow down and retry later (`rate_limited`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class RateLimitedException extends Exception implements TwoMailException
{
}
