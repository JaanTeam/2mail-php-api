<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * Conflicts with the current state — a duplicate group name, or a message too far along to cancel (`conflict`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class ConflictException extends Exception implements TwoMailException
{
}
