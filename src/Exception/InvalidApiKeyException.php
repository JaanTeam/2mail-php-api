<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The API key is unknown, disabled or revoked (code `invalid_api_key`).
 *
 * The exception code is the HTTP status the API answered with.
 */
final class InvalidApiKeyException extends Exception implements TwoMailException
{
}
