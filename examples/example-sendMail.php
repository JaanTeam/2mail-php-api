<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Message;
use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// The short way: address, recipient, subject, body.
$result = $api->sendMail(
    $fromEmail,
    $toEmail,
    'Hello from the 2mail API',
    '<p>Hi there!</p>'
);

var_dump($result->id(), $result->status());

// The same thing built up field by field. Note that the sender address and the sender's
// display name are two arguments of one call — never one combined string.
$result = $api->send(
    Message::create()
        ->from($fromEmail, 'Acme')
        ->to($toEmail)
        ->subject('Hello again')
        ->html('<p>Hi there!</p>')
        ->text('Hi there!')
);

var_dump($result->id(), $result->status());

// A 202 means ACCEPTED, not delivered — see example-messageStatus.php for the outcome.
