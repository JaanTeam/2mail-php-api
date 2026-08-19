<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * That HTTP method is not supported on this endpoint (`method_not_allowed`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class MethodNotAllowedException extends Exception implements TwoMailException
{
}
