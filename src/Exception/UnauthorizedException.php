<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * Authentication failed and carries no more specific code (`unauthorized`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class UnauthorizedException extends Exception implements TwoMailException
{
}
