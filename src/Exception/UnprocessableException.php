<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * Understood but not processable, with no more specific code (`unprocessable`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class UnprocessableException extends Exception implements TwoMailException
{
}
