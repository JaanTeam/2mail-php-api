<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * A display name was sent BOTH inside "from" and in "from_name", with different values (code `conflicting_from_name`).
 *
 * Put the address in "from" and the display name in "from_name" — Message::from($email, $name)
 * always does that for you. The API refuses rather than picking one, because either choice
 * would send mail under a name the caller did not ask for.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class ConflictingFromNameException extends Exception implements TwoMailException
{
}
