<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The html + text body exceeds the 2 MB limit (code `body_too_large`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class BodyTooLargeException extends Exception implements TwoMailException
{
}
