# 2MAIL PHP API

This library connects to the 2mail API using PHP — https://www.2mail.eu/e-mail-api

Send email from your own application, follow what happens to it, and provision sending
domains, SMTP credentials, pooled limit groups, API keys and webhooks.

## Installation

Integrate this repository in your composer.json

```json
{
    "require": {
        "jaanbv/2mail-php-api": "^1.*"
    },
    "repositories": [
        {
            "type": "vcs",
            "url":  "https://github.com/jaanteam/2mail-php-api.git"
        }
    ]
}
```

Requires PHP 8.1 with `ext-curl` and `ext-json`. No other dependencies.

> The PHP namespace is `JaanBV\TwoMail`, not `JaanBV\2mail`: a PHP identifier cannot start
> with a digit. The composer package and the repository keep the `2mail` name.

## Getting a key

Keys are created in the 2mail control panel under **Settings → API keys**, and come in
three scopes. The API enforces them server-side, so a key can never do more than its scope
allows — including on endpoints added after the key was issued.

| Scope | What it reaches |
|---|---|
| **Mailbox** (send-only) | `send`, `messageStatus`, `cancelMessage`, `events` — for one mailbox. Everything else is a `403`. |
| **Pool** | Everything inside one limit group: its mailboxes, their credentials, its webhooks and its send-only keys. |
| **Admin** | Everything, across all pools. |

**Put a mailbox key in your application.** It cannot provision domains, issue SMTP
passwords, mint keys, read another tenant's mail or change anything — so a leak from an
application server stays a sending problem rather than an account takeover.

## Sending

```php
use JaanBV\TwoMail\TwoMail;

$api = new TwoMail($apiKey);

$result = $api->sendMail(
    'no-reply@acme.com',          // from address — must be an allowed domain of the mailbox
    'customer@example.com',       // to: one address, or an array
    'Your order',
    '<p>Thanks!</p>'              // html; add text as the next argument
);

echo $result->id();               // poll this, or wait for a webhook
```

Anything beyond that — copies, reply-to, scheduling, test mode — goes through a `Message`:

```php
use JaanBV\TwoMail\Message;

$result = $api->send(
    Message::create()
        ->from('no-reply@acme.com', 'Acme')
        ->to(['customer@example.com', 'second@example.com'])
        ->cc('manager@example.com')
        ->bcc('archive@example.com')
        ->replyTo('support@example.com')
        ->subject('Your order')
        ->html('<p>Thanks!</p>')
        ->text('Thanks!')
        ->tracking(false)
        ->sendAt(new DateTimeImmutable('+2 hours'))
);
```

### The sender address and the sender name are two arguments

```php
->from('no-reply@acme.com', 'Acme')     // recipient sees: Acme <no-reply@acme.com>
```

The API also accepts the combined `"Acme <no-reply@acme.com>"` spelling for older clients,
but sending a display name in *both* places with different values is refused
(`ConflictingFromNameException`) rather than one being silently chosen for you. Going
through `from($email, $name)` that situation cannot arise.

Leave the name empty and the recipient sees the bare address. The mailbox's *campaign*
sender name is deliberately not inherited, so transactional mail never goes out signed with
a marketing identity.

### A 202 is "accepted", not "delivered"

`send()` returns as soon as 2mail has taken the message; the relay delivers it a moment
later. The outcome arrives through `messageStatus()`, `events()`, or a webhook:

```php
$status = $api->messageStatus($result->id());

$status->isQueued();     // still waiting — the only state in which it can be cancelled
$status->isSent();       // handed to the receiving server (which can still bounce it)
$status->isFailed();
$status->isFinished();   // stop polling
```

A scheduled message can be withdrawn while it is still queued:

```php
$api->cancelMessage($result->id());
```

### Retrying safely

If a send times out you cannot tell whether it was queued: retrying risks a duplicate
order confirmation, not retrying risks losing it. An idempotency key removes the choice —
a replay returns the **original** result and queues nothing:

```php
$api->send($message, 'invoice-4711');
```

Pick something stable per logical message. **An order id beats a random UUID**, which
changes on every retry and therefore protects nothing at all.

- Replaying the key → the original result, no second message.
- Same key, different message → `IdempotencyKeyReuseException` (a client bug, not a retry).
- Replay while the first call is still running → `IdempotencyInProgressException`.
- A *failed* call releases its key, so retrying after a transient error is a fresh attempt
  rather than a replay of the failure.

Keys are scoped per API key and kept for 7 days.

### Trying it out without sending

```php
$result = $api->send($message->test());

$result->isTest();   // true
$result->status();   // "validated"
$result->id();       // null — nothing was stored, so there is nothing to look up
```

Every check runs — key scope, from-domain, recipients, body, schedule — and nothing is
queued or sent. This is the right way to exercise your integration in a test suite: it runs
the real authorization and validation, which a local mock cannot.

## Events

```php
use JaanBV\TwoMail\Event\EventType;

foreach ($api->events(['event' => EventType::BOUNCED, 'begin' => '2026-08-01']) as $event) {
    $event->recipient();
    $event->detail();      // the receiving server's response
    $event->isFailure();
}
```

Filters: `event`, `mailbox`, `recipient`, `begin`, `end`, `limit` (max 300). Defaults to the
last 7 days. A mailbox key always sees only its own mailbox — asking for another one is
refused, not quietly ignored.

## Webhooks

The push side of the same log. Register a URL, keep the secret, verify every delivery:

```php
$webhook = $api->createWebhook('https://example.com/2mail/webhook', [
    EventType::DELIVERED,
    EventType::BOUNCED,
]);

$webhook['secret'];   // shown ONCE — store it now
```

Your webhook URL is public, so anyone who learns it can POST to it. The signature is what
separates a real event from someone else's invention — verify **before** acting on the
payload:

```php
use JaanBV\TwoMail\Exception\InvalidWebhookSignatureException;
use JaanBV\TwoMail\WebhookSignature;

$payload = file_get_contents('php://input');   // the RAW body

try {
    $event = WebhookSignature::event($secret, $_SERVER['HTTP_X_2MAIL_SIGNATURE'] ?? '', $payload);
} catch (InvalidWebhookSignatureException $exception) {
    http_response_code(403);
    exit;
}
```

Sign the raw body byte for byte: decoding the JSON and re-encoding it changes spacing and
escaping, and the signature will never match. The check also rejects a delivery whose
timestamp is more than 5 minutes old, which is what stops a captured request being replayed
forever.

Answer 2xx quickly and process afterwards — anything else is retried with backoff, so a
slow handler collects duplicates. Make the handler idempotent on `messageId()` + `event()`.

## Provisioning

Admin and pool keys only.

```php
$created = $api->createMailbox('acme.com', 'Acme', 'news@acme.com', [
    'allowed_domains' => 'acme.com,shop.acme.com',
    'monthly_limit'   => 100000,
]);

$created['credential']['password'];             // shown once

$api->mailboxDnsRecords($created['mailbox']['id']);   // SPF/DKIM/DMARC + verified flags
$api->mailboxUsage($created['mailbox']['id']);        // this month, per credential

$api->createCredential($mailboxId, 'billing-app');    // one SMTP login per application
$api->createMailboxApiKey($mailboxId, 'Webshop orders');
```

A key can never hand out more authority than it holds: an admin key may create pool and
mailbox keys, a pool key only mailbox keys inside its own pool, and no key can create an
admin key. That stays a control-panel action.

## Address validation

```php
$check = $api->validateEmail('user@gmial.com');

$check['result'];        // deliverable | undeliverable | risky | unknown
$check['did_you_mean'];  // "user@gmail.com"

$api->isValidEmail($email);        // true only for "deliverable"
$api->validateEmail($email, true); // deep: adds an SMTP probe — slower, may be inconclusive
```

## Errors

Every failure raises an exception named after what went wrong, so you branch on the class
rather than on a message that may be reworded:

```php
use JaanBV\TwoMail\Exception\QuotaExceededException;
use JaanBV\TwoMail\Exception\TwoMailException;

try {
    $api->send($message);
} catch (QuotaExceededException $exception) {
    // out of monthly credit — queue it for next month
} catch (TwoMailException $exception) {
    // anything else the API or the transport refused
}
```

Every exception in this library implements `TwoMailException`, and `getCode()` is the HTTP
status. `$api->lastRequestId()` returns the id 2mail logged the call under — quote it in a
support request and the exact call can be found.

`InvalidResponseCodeException` means the API returned an error this library does not know
yet: the call was fine, the SDK is simply older than the API.

Redirects are **not** followed. cURL would replay the `Authorization` header on the
redirect target, handing your API key to whatever host the `Location` points at — so a
misconfigured base URL raises `InvalidHttpResponseCodeException` instead.

## TwoMail functions

**Sending**

- send($message [Message], $idempotencyKey = null) : Result
- sendMail($fromEmail, $to, $subject, $html = null, $text = null, $fromName = '', $idempotencyKey = null) : Result
- messageStatus($messageId) : Status
- cancelMessage($messageId) : bool
- events($filters = []) : Event[]

**Mailboxes**

- mailboxes() / mailbox($mailboxId)
- createMailbox($domain, $fromName, $fromEmail, $options = [])
- mailboxUsage($mailboxId) / mailboxDnsRecords($mailboxId)
- assignMailboxToGroup($mailboxId, $groupId) / removeMailboxFromGroup($mailboxId)

**SMTP credentials**

- credentials($mailboxId)
- createCredential($mailboxId, $label)
- deleteCredential($mailboxId, $credentialId)

**Limit groups (pools)**

- groups() / createGroup($name, $monthlyLimit)
- groupUsers($groupId)
- grantGroupAccess($groupId, $userId = null, $email = null, $demoMode = false)
- revokeGroupAccess($groupId, $userId)

**API keys**

- apiKeys() / apiKey($keyId)
- createMailboxApiKey($mailboxId, $label) / createPoolApiKey($groupId, $label)
- enableApiKey($keyId, $enabled) / deleteApiKey($keyId)

**Webhooks**

- webhooks() / webhook($webhookId)
- createWebhook($url, $events = [], $groupId = null)
- updateWebhook($webhookId, $changes) / rotateWebhookSecret($webhookId)
- deleteWebhook($webhookId)

**Validation and diagnostics**

- validateEmail($email, $deep = false) / isValidEmail($email, $deep = false)
- ping() / serviceInfo() / lastRequestId() / enableDebugging()

## Message functions

- create()
- from($email, $name = '')
- to($email) / cc($email) / bcc($email) — a string or an array, and they accumulate
- replyTo($email)
- subject($subject) / html($html) / text($text)
- sendAt($dateTime [DateTimeInterface])
- tracking($on) — per-message open-tracking override
- test($test = true) — validate only, send nothing

## Examples

- [Sending mail](examples/example-sendMail.php)
- [Copies, scheduling, idempotency, test mode](examples/example-sendMailAdvanced.php)
- [Delivery status](examples/example-messageStatus.php)
- [Reading events](examples/example-events.php)
- [Managing webhooks](examples/example-webhooks.php)
- [Receiving a webhook](examples/example-webhookEndpoint.php)
- [Creating a mailbox](examples/example-createMailbox.php)
- [SMTP credentials](examples/example-smtpCredentials.php)
- [Limit groups](examples/example-groups.php)
- [API keys](examples/example-apiKeys.php)
- [Validating an address](examples/example-validateEmail.php)
- [Checking a key](examples/example-ping.php)

Add your API key in `examples/credentials.php` first, then:

```bash
# Send an e-mail
php examples/example-sendMail.php
```

## PHPUnit

Executing all tests:

```bash
./vendor/bin/phpunit tests
```

## Interactive API docs

Swagger UI, with a "try it out" console that makes real calls against the live API using a
key you paste: https://www.2mail.eu/2mail/api/docs
