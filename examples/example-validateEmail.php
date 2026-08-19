<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// Syntax, MX, disposable/role flags and a typo suggestion.
$check = $api->validateEmail('user@gmial.com');

var_dump(
    $check['result'],       // deliverable | undeliverable | risky | unknown
    $check['detail'],
    $check['did_you_mean']  // "user@gmail.com" — worth showing on a signup form
);

// Deep mode adds an SMTP probe of the receiving server. Slower, and inconclusive when port
// 25 is blocked — which is exactly why the verdict may come back as "unknown" rather than
// a straight yes or no.
var_dump($api->validateEmail($toEmail, true));

// The shorthand is true ONLY for a "deliverable" verdict, so "risky" and "unknown" both
// come back false. Read the full result yourself if you would rather decide per category
// than turn away a valid address that merely could not be probed.
var_dump($api->isValidEmail($toEmail));
