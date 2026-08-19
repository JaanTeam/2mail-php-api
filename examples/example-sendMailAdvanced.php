<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Exception\IdempotencyKeyReuseException;
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
// Pass an idempotency key and the call is safe to retry: a replay returns the ORIGINAL
// result instead of queueing a second message. Choose something stable per logical
// message — an order id beats a random UUID, which changes on every retry and so protects
// nothing at all.
$result = $api->send($message, 'invoice-4711');

var_dump($result->id(), $result->status(), $result->scheduledFor());

// Reusing that key for a DIFFERENT message is a client bug, and is refused rather than
// answered with the earlier unrelated result.
try {
    $api->send(
        Message::create()
            ->from($fromEmail)
            ->to($toEmail)
            ->subject('Something else entirely')
            ->text('...')
            ->sendAt(new DateTimeImmutable('+2 hours')),
        'invoice-4711'
    );
} catch (IdempotencyKeyReuseException $exception) {
    echo 'Refused, as it should be: ' . $exception->getMessage() . PHP_EOL;
}

// ---- Withdrawing it again -------------------------------------------------------------
// A scheduled message can still be cancelled, as long as the relay has not claimed it.
var_dump($api->cancelMessage($result->id()));
