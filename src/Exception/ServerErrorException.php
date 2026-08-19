<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The API failed on its side. Quote the request id when reporting it (`server_error`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class ServerErrorException extends Exception implements TwoMailException
{
}
