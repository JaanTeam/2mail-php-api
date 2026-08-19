<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * cURL could not complete the request at all — DNS, TLS, connection or timeout failure.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class TransportException extends Exception implements TwoMailException
{
}
