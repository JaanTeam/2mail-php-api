<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// This month so far, per day (the default). A mailbox key sees its own mailbox only.
$stats = $api->stats();

$quota = $stats['quota'];
if ($quota !== null) {
    printf('Quota %s: %d of %d used, %d left%s', $quota['month'], $quota['used'], $quota['limit'], $quota['left'], PHP_EOL);
}

$t = $stats['totals'];
printf(
    'Sent %d, delivered %d, bounced %d, rejected %d, delivery rate %s%%%s',
    $t['sent'],
    $t['delivered'],
    $t['bounced'],
    $t['rejected'],
    $t['delivery_rate'] ?? '-',
    PHP_EOL
);

foreach ($stats['series'] as $day) {
    // null means "no longer available" (older than the detailed logs are kept), not zero.
    printf('%s  sent %5d  delivered %5s  opened %5s%s', $day['date'], $day['sent'], $day['delivered'] ?? '-', $day['opened'] ?? '-', PHP_EOL);
}

// The last 12 months, from the permanent monthly totals — use this for anything older
// than a few weeks.
foreach ($api->stats(['group_by' => 'month'])['series'] as $month) {
    printf('%s  sent %6d  delivered %6d  bounced %5d%s', $month['month'], $month['sent'], $month['delivered'], $month['bounced'], PHP_EOL);
}

// A chosen period: at most 31 days per day, at most 24 months per month.
$october = $api->stats(['begin' => '2026-10-01', 'end' => '2026-10-31']);
if (isset($october['available_from'])) {
    // Part of the period is older than the detailed logs: these metrics are complete from
    // the given day on.
    print_r($october['available_from']);
}
