<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * No API key was sent. Add an Authorization: Bearer header (code `missing_api_key`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class MissingApiKeyException extends Exception implements TwoMailException
{
}
