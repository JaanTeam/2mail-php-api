<?php

// add your own credentials in this file
require_once __DIR__ . '/credentials.php';

// required to load (only when not using an autoloader)
require_once __DIR__ . '/../vendor/autoload.php';

use JaanBV\TwoMail\Event\EventType;
use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

// Subscribe a URL to the events you care about. Leave the list empty for all of them.
$webhook = $api->createWebhook(
    'https://example.com/2mail/webhook',
    [EventType::DELIVERED, EventType::BOUNCED, EventType::REJECTED]
);

// Shown ONCE. Store it now — it cannot be read back, only rotated.
echo 'Signing secret: ' . $webhook['secret'] . PHP_EOL;

var_dump($api->webhooks());

// Pause deliveries without losing the subscription.
$api->updateWebhook($webhook['id'], ['enabled' => false]);

// Change what it listens to.
$api->updateWebhook($webhook['id'], ['events' => [EventType::BOUNCED], 'enabled' => true]);

// Lost the secret? Rotate it — the new one is returned once, and the old one stops
// verifying immediately. Deploy the new secret first: deliveries arriving in between will
// fail verification. They are retried, so nothing is lost, but your endpoint will reject
// those attempts until the new secret is live.
$rotated = $api->rotateWebhookSecret($webhook['id']);

echo 'New signing secret: ' . $rotated['secret'] . PHP_EOL;

$api->deleteWebhook($webhook['id']);

// See example-webhookEndpoint.php for the receiving side.
