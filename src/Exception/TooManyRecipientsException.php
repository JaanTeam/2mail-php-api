<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * More than 50 recipients across to + cc + bcc (code `too_many_recipients`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class TooManyRecipientsException extends Exception implements TwoMailException
{
}
