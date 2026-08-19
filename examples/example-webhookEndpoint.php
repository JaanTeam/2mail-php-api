<?php

/**
 * The receiving end of a webhook — this is the file you point the subscription URL at.
 *
 * Your webhook URL is public: anyone who learns it can POST to it. The signature is the
 * only thing separating a real event from someone else's invention, so verify it BEFORE
 * acting on the payload.
 */

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Event\EventType;
use JaanBV\TwoMail\Exception\InvalidWebhookSignatureException;
use JaanBV\TwoMail\WebhookSignature;

// The secret shown once when the webhook was created. Keep it out of your source tree.
$secret = getenv('TWOMAIL_WEBHOOK_SECRET') ?: '';

// The RAW body, byte for byte. Decoding the JSON and re-encoding it changes spacing and
// escaping, and the signature will then never match.
$payload = file_get_contents('php://input');

try {
    $event = WebhookSignature::event(
        $secret,
        $_SERVER['HTTP_X_2MAIL_SIGNATURE'] ?? '',
        $payload
    );
} catch (InvalidWebhookSignatureException $exception) {
    http_response_code(403);
    error_log('2mail webhook rejected: ' . $exception->getMessage());
    exit;
}

// Answer quickly, then do the work. Anything other than a 2xx is retried with backoff, so
// a handler that finishes its processing before replying will collect duplicates.
http_response_code(200);

switch ($event->event()) {
    case EventType::BOUNCED:
        // Hard bounce: stop mailing this address. Every further attempt damages the
        // reputation of your sending domain, and with it everything else you send.
        // suppress($event->recipient());
        break;

    case EventType::DELIVERED:
        // markDelivered($event->messageId());
        break;

    case EventType::OPENED:
        // countOpen($event->messageId());
        break;
}

// Deliveries can repeat — a retry after a timeout on your side, for instance. Make the
// handler idempotent: keying on $event->messageId() plus $event->event() is usually enough.
