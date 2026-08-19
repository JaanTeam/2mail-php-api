<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Exception\NotFoundException;
use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

$result = $api->sendMail($fromEmail, $toEmail, 'Status example', '<p>Hi!</p>');

// Sending is asynchronous: the relay picks the message up a moment later. Poll until the
// message is finished — or, better for anything long-lived, subscribe to a webhook and
// let 2mail tell you (see example-webhooks.php).
for ($attempt = 0; $attempt < 10; $attempt++) {
    $status = $api->messageStatus($result->id());

    echo $status->status() . PHP_EOL;

    if ($status->isFinished()) {
        break;
    }

    sleep(2);
}

var_dump(
    $status->status(),      // sent | failed | queued | sending
    $status->messageId(),   // the RFC Message-ID, once it has gone out
    $status->sentAt(),
    $status->error()        // why it failed, when it did
);

// "sent" means the relay handed it on — the receiving server can still bounce it
// afterwards. A bounce is an event, not a status: see example-events.php.

// Cancelling removes the message outright, so looking it up afterwards is a 404:
try {
    $api->messageStatus('0000000000000000000000000000dead');
} catch (NotFoundException $exception) {
    echo 'Unknown message: ' . $exception->getMessage() . PHP_EOL;
}
