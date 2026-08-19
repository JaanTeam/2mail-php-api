<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * A send-only mailbox key tried to reach a route outside its allowlist (code `key_scope_mailbox`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class KeyScopeMailboxException extends Exception implements TwoMailException
{
}
