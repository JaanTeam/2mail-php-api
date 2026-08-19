<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * The call did not reach the API, or did not come back as JSON.
 *
 * Typically a wrong base URL, a redirect (which is NOT followed — that would leak the
 * Authorization header to another host), or a WAF/proxy answering with an HTML error page.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class InvalidHttpResponseCodeException extends Exception implements TwoMailException
{
}
