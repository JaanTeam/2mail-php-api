<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The API key was valid but has passed its expiry date (code `api_key_expired`).
 *
 * Unlike InvalidApiKeyException this is only raised for the full, correct key, so the fix
 * is known: extend the key or create a new one in the control panel. The message names
 * the expiry date.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class ApiKeyExpiredException extends Exception implements TwoMailException
{
}
