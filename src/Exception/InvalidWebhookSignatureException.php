<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

use Exception;

/**
 * An incoming webhook could not be verified against the signing secret.
 *
 * Treat the payload as untrusted and do not act on it.
 *
 * The exception code is the HTTP status the API answered with.
 */
final class InvalidWebhookSignatureException extends Exception implements TwoMailException
{
}
