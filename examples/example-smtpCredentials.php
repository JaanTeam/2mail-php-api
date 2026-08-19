<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

var_dump($api->credentials($mailboxId));

// One SMTP login per application rather than one shared password: a leak can then be
// revoked on its own, and the usage breakdown shows which application is sending what.
$credential = $api->createCredential($mailboxId, 'billing-app');

echo 'SMTP username: ' . $credential['username'] . PHP_EOL;
echo 'SMTP password: ' . $credential['password'] . PHP_EOL; // shown once

// Revoking is immediate: anything still using this login stops sending.
var_dump($api->deleteCredential($mailboxId, $credential['id']));
