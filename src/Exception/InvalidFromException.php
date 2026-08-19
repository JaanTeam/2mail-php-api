<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The "from" address is missing or not a valid email address (code `invalid_from`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class InvalidFromException extends Exception implements TwoMailException
{
}
