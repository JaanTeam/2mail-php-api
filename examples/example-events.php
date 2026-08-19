<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Event\EventType;
use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// Everything from the last 7 days (the default window).
foreach ($api->events() as $event) {
    printf(
        "%-20s %-10s %s%s",
        $event->timestamp(),
        $event->event(),
        $event->recipient(),
        PHP_EOL
    );
}

// Only what went wrong, for one recipient, over a chosen period.
$bounces = $api->events([
    'event' => EventType::BOUNCED,
    'recipient' => $toEmail,
    'begin' => '2026-08-01',
    'end' => '2026-08-31',
    'limit' => 300,
]);

foreach ($bounces as $event) {
    // Stop mailing an address that hard-bounces: every further attempt damages the
    // reputation of your sending domain, and with it the delivery of everything else.
    printf('%s bounced: %s (%s)%s', $event->recipient(), $event->detail(), $event->code(), PHP_EOL);
}

// This is the pull side of the same log webhooks push. Polling is fine for a report or a
// nightly clean-up; for reacting to a bounce as it happens, use a webhook instead.
