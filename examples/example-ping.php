<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// True for any working key, whatever its scope. False only when the key itself is
// rejected — a connection failure still throws, because "the network is down" is not an
// answer to "is this key valid".
var_dump($api->ping());

// What this particular key is allowed to reach. A send-only mailbox key sees a short list.
var_dump($api->serviceInfo());

// Every response carries a request id. Quote it in a support request and the exact call
// can be found in the 2mail activity log.
echo 'Request id: ' . $api->lastRequestId() . PHP_EOL;
