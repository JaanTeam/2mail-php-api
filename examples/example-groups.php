<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

// Creating groups is an admin-key action; a pool key sees only its own group.
$api = new TwoMail($apiKey);

// A pool lets several mailboxes share one monthly limit — one customer with several
// sending domains, or a reseller with several customers.
$group = $api->createGroup('Acme', 100000);

var_dump($api->groups());

// Mailboxes moved into a pool draw from the pool's limit; their own monthly_limit is
// ignored for as long as they are in it.
$api->assignMailboxToGroup($mailboxId, $group['id']);

// Give a control-panel login access to every mailbox in the pool, present and future.
// Identify the account by user id or by email.
$api->grantGroupAccess($group['id'], null, 'customer@acme.com');

var_dump($api->groupUsers($group['id']));

// Take it all back out again.
$api->removeMailboxFromGroup($mailboxId);
