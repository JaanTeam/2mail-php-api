<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The mailbox, credential, group, key, webhook or message does not exist (`not_found`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class NotFoundException extends Exception implements TwoMailException
{
}
