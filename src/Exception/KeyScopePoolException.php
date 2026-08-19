<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The key is scoped to a different pool (limit group) than the resource (code `key_scope_pool`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class KeyScopePoolException extends Exception implements TwoMailException
{
}
