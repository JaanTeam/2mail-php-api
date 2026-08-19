<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The API returned an error code this SDK version does not know yet.
 *
 * The call itself was fine; the SDK is simply older than the API. Please contact a developer
 * or create a merge request to add it.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class InvalidResponseCodeException extends Exception implements TwoMailException
{
}
