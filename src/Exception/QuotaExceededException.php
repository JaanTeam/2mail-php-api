<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * Sending is disabled for the mailbox: monthly limit reached, or switched off (code `quota_exceeded`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class QuotaExceededException extends Exception implements TwoMailException
{
}
