<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * A request with this Idempotency-Key is still running (code `idempotency_in_progress`).
 *
 * Wait and retry the same key: once the first call finishes, the replay returns its result.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class IdempotencyInProgressException extends Exception implements TwoMailException
{
}
