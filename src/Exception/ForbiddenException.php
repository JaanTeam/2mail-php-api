<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * Out of the key's scope, with no more specific code (`forbidden`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class ForbiddenException extends Exception implements TwoMailException
{
}
