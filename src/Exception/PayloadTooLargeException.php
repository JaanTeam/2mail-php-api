<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The request body is too large (`payload_too_large`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class PayloadTooLargeException extends Exception implements TwoMailException
{
}
