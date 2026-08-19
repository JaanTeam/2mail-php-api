<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The Idempotency-Key header is malformed or longer than 255 characters (code `idempotency_key_invalid`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class IdempotencyKeyInvalidException extends Exception implements TwoMailException
{
}
