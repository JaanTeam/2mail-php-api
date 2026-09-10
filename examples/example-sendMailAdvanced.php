<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Exception\IdempotencyInProgressException;
use JaanBV\TwoMail\Exception\IdempotencyKeyReuseException;
use JaanBV\TwoMail\Exception\TransportException;
use JaanBV\TwoMail\Message;
use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// ---- Validate without sending ---------------------------------------------------------
// Every check runs — key scope, from-domain, recipients, body, schedule — but nothing is
// queued and nothing is sent. Useful in a test suite that must not email real people.
$result = $api->send(
    Message::create()
        ->from($fromEmail, 'Acme')
        ->to($toEmail)
        ->subject('Dry run')
        ->text('This is never sent.')
        ->test()
);

var_dump($result->isTest(), $result->status(), $result->note());

// Nothing was stored, so there is no id to look up afterwards:
var_dump($result->id()); // NULL

// ---- Copies, reply-to and scheduling --------------------------------------------------
$message = Message::create()
    ->from($fromEmail, 'Acme')
    ->to([$toEmail, 'second@example.com'])
    ->cc('manager@example.com')
    ->bcc('archive@example.com')          // stripped from the delivered headers
    ->replyTo('support@example.com')
    ->subject('Your invoice')
    ->html('<p>Attached to your account.</p>')
    ->text('Attached to your account.')
    ->tracking(false)                     // no open-tracking pixel for this one
    ->sendAt(new DateTimeImmutable('+2 hours'));

// ---- Retrying safely ------------------------------------------------------------------
// The key names the ATTEMPT, not the message: it answers "have I already tried to send
// this?", not "is this email unique?". So it is created once, before the first try, and
// reused unchanged on every retry of that attempt — regenerate it per retry and it protects
// nothing at all. A replay then returns the ORIGINAL result instead of queueing a second
// message.
$key    = bin2hex(random_bytes(16));      // ONCE, outside the loop — that is the whole point
$result = null;

for ($attempt = 1; $attempt <= 3; $attempt++) {
    try {
        $result = $api->send($message, $key);
        break;                            // queued — or replayed from an earlier attempt
    } catch (IdempotencyInProgressException $exception) {
        sleep($attempt);                  // attempt 1 is still running: same key again
    } catch (TransportException $exception) {
        sleep($attempt * 2);              // timeout or network error: same key again
    }
}

if ($result === null) {
    // The key was released by the failures, so it stays usable for a later retry.
    exit('Three attempts failed; nothing was queued.' . PHP_EOL);
}

var_dump($result->id(), $result->status(), $result->scheduledFor());

// Sending the same message again ON PURPOSE — a customer asking you to resend — needs a NEW
// key. Reusing this one would replay the result above and mail nothing at all.

// Reusing it for a DIFFERENT message is a client bug, and is refused rather than answered
// with the earlier unrelated result.
try {
    $api->send(
        Message::create()
            ->from($fromEmail)
            ->to($toEmail)
            ->subject('Something else entirely')
            ->text('...')
            ->sendAt(new DateTimeImmutable('+2 hours')),
        $key
    );
} catch (IdempotencyKeyReuseException $exception) {
    echo 'Refused, as it should be: ' . $exception->getMessage() . PHP_EOL;
}

// ---- Withdrawing it again -------------------------------------------------------------
// A scheduled message can still be cancelled, as long as the relay has not claimed it.
var_dump($api->cancelMessage($result->id()));
