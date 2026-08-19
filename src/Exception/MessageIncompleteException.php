<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The message is missing something the API requires, caught before the call is made.
 *
 * A "from" address, at least one recipient, a subject, and an html and/or text body.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class MessageIncompleteException extends Exception implements TwoMailException
{
}
