<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// A send-only key for one mailbox: it can send, check status, cancel and read its own
// events, and nothing else. This is the key that belongs in an application — it cannot
// provision, issue SMTP credentials, manage webhooks, mint keys, or see another account.
$key = $api->createMailboxApiKey($mailboxId, 'Webshop orders');

echo 'Key (shown once): ' . $key['key'] . PHP_EOL;

// A key can never hand out more authority than it holds: an admin key may create pool and
// mailbox keys, a pool key only mailbox keys inside its own pool, and nobody can create an
// admin key through the API. That is why there is no createAdminApiKey() here.
$poolKey = $api->createPoolApiKey(1, 'Reseller: Acme');

// Only the prefix is ever returned — enough to identify a key, useless for authenticating.
var_dump($api->apiKeys());
var_dump($api->apiKey($key['id']));

// Pause a key without revoking it — handy while investigating unexpected traffic.
$api->enableApiKey($key['id'], false);
$api->enableApiKey($key['id'], true);

// Revoking is immediate and cannot be undone. A key can neither disable nor delete itself.
var_dump($api->deleteApiKey($key['id']));
