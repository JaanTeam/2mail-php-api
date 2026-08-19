<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

// Provisioning needs an admin or pool key; a send-only mailbox key gets a 403 here.
$api = new TwoMail($apiKey);

// Onboard a sending domain. Returns the mailbox and its first SMTP credential.
$created = $api->createMailbox(
    'acme.com',
    'Acme',
    'news@acme.com',
    [
        'allowed_domains' => 'acme.com,shop.acme.com',
        'monthly_limit' => 100000,
        'credential_label' => 'primary',
    ]
);

var_dump($created['mailbox']);

// The SMTP password is shown ONCE and is not stored in readable form anywhere. If it is
// lost, issue a new credential and revoke this one — see example-smtpCredentials.php.
echo 'SMTP username: ' . $created['credential']['username'] . PHP_EOL;
echo 'SMTP password: ' . $created['credential']['password'] . PHP_EOL;

var_dump($api->mailboxes());
var_dump($api->mailbox($created['mailbox']['id']));

// Mail from an unverified domain is delivered, but far more of it lands in spam. Show
// these records in your own onboarding screen and let the customer publish them.
var_dump($api->mailboxDnsRecords($created['mailbox']['id']));

// This month, pooled if the mailbox is in a limit group, with a per-credential breakdown
// so you can see which application is spending the limit.
var_dump($api->mailboxUsage($created['mailbox']['id']));
