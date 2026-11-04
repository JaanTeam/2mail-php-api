<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The key has an IP restriction and the call came from an address outside it
 * (code `ip_not_allowed`).
 *
 * The message names the address the API saw, which is the one to add to the key's IP
 * restriction in the control panel. Behind NAT or a proxy that is the outgoing address,
 * not the server's own.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class IpNotAllowedException extends Exception implements TwoMailException
{
}
