<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The message has no "to" recipient (code `no_recipients`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class NoRecipientsException extends Exception implements TwoMailException
{
}
