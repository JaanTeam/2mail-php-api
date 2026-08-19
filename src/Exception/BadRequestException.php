<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The request was rejected as invalid and carries no more specific code (`bad_request`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class BadRequestException extends Exception implements TwoMailException
{
}
