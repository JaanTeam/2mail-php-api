<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The same Idempotency-Key was reused with a DIFFERENT request body (code `idempotency_key_reuse`).
 *
 * That is a client bug rather than a retry, so the API refuses instead of replaying an
 * unrelated earlier result. Use a key that is stable per logical message.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class IdempotencyKeyReuseException extends Exception implements TwoMailException
{
}
