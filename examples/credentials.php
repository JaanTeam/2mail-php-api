<?php

// define your own credentials
$apiKey = ''; // required — a 2m_live_… key from Settings → API keys

// only needed for the provisioning examples; a send-only mailbox key cannot use them
$mailboxId = 0;

// a domain your mailbox is allowed to send from
$fromEmail = 'no-reply@example.com';

// where the example messages go
$toEmail = 'someone@example.com';

// throw error
if (empty($apiKey)) {
    echo 'Please define your login credentials in ' . __DIR__ . '/credentials.php';
}
