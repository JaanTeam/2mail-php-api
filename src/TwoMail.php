<?php

declare(strict_types=1);

namespace JaanBV\TwoMail;

use JaanBV\TwoMail\Event\Event;
use JaanBV\TwoMail\Exception\BadRequestException;
use JaanBV\TwoMail\Exception\BodyTooLargeException;
use JaanBV\TwoMail\Exception\ConflictException;
use JaanBV\TwoMail\Exception\ConflictingFromNameException;
use JaanBV\TwoMail\Exception\ForbiddenException;
use JaanBV\TwoMail\Exception\IdempotencyInProgressException;
use JaanBV\TwoMail\Exception\IdempotencyKeyInvalidException;
use JaanBV\TwoMail\Exception\IdempotencyKeyReuseException;
use JaanBV\TwoMail\Exception\InvalidApiKeyException;
use JaanBV\TwoMail\Exception\InvalidFromException;
use JaanBV\TwoMail\Exception\InvalidHttpResponseCodeException;
use JaanBV\TwoMail\Exception\InvalidResponseCodeException;
use JaanBV\TwoMail\Exception\KeyScopeMailboxException;
use JaanBV\TwoMail\Exception\KeyScopePoolException;
use JaanBV\TwoMail\Exception\MethodNotAllowedException;
use JaanBV\TwoMail\Exception\MissingApiKeyException;
use JaanBV\TwoMail\Exception\NoRecipientsException;
use JaanBV\TwoMail\Exception\NotFoundException;
use JaanBV\TwoMail\Exception\PayloadTooLargeException;
use JaanBV\TwoMail\Exception\QuotaExceededException;
use JaanBV\TwoMail\Exception\RateLimitedException;
use JaanBV\TwoMail\Exception\ServerErrorException;
use JaanBV\TwoMail\Exception\TooManyRecipientsException;
use JaanBV\TwoMail\Exception\TransportException;
use JaanBV\TwoMail\Exception\TwoMailException;
use JaanBV\TwoMail\Exception\UnauthorizedException;
use JaanBV\TwoMail\Exception\UnprocessableException;
use JaanBV\TwoMail\SentMessage\Result;
use JaanBV\TwoMail\SentMessage\Status;

/**
 * TwoMail
 *
 * This 2mail PHP Class connects to the 2mail API (https://www.2mail.eu/e-mail-api).
 *
 * Sending
 * - send
 * - sendMail
 * - messageStatus
 * - cancelMessage
 * - events
 *
 * Provisioning (admin and pool keys)
 * - mailboxes / mailbox / createMailbox
 * - mailboxUsage / mailboxDnsRecords
 * - assignMailboxToGroup / removeMailboxFromGroup
 * - credentials / createCredential / deleteCredential
 * - groups / createGroup / groupUsers / grantGroupAccess / revokeGroupAccess
 * - apiKeys / apiKey / createMailboxApiKey / createPoolApiKey / enableApiKey / deleteApiKey
 * - webhooks / webhook / createWebhook / updateWebhook / rotateWebhookSecret / deleteWebhook
 * - validateEmail / isValidEmail
 *
 * What a key may call depends on its scope, and the API enforces that server-side:
 * an ADMIN key reaches everything; a POOL key is confined to its own limit group; a
 * MAILBOX key is send-only — send, status, cancel and events for its own mailbox, and
 * ForbiddenException for the rest. There is deliberately no client-side mirror of those
 * rules: a second copy would only drift out of step with the real one.
 */
final class TwoMail
{
    /**
     * Production. Pass a different base to the constructor for a staging environment.
     */
    public const API_URL = 'https://www.2mail.eu/2mail/api/v1';

    private const API_ENDPOINT_FOR_SERVICE_INFO = '';
    private const API_ENDPOINT_FOR_MAILBOXES = 'mailboxes';
    private const API_ENDPOINT_FOR_GROUPS = 'groups';
    private const API_ENDPOINT_FOR_MESSAGES = 'messages';
    private const API_ENDPOINT_FOR_EVENTS = 'events';
    private const API_ENDPOINT_FOR_KEYS = 'keys';
    private const API_ENDPOINT_FOR_WEBHOOKS = 'webhooks';
    private const API_ENDPOINT_FOR_VALIDATION = 'validate';

    private const KEY_FOR_CODE = 'code';
    private const KEY_FOR_ERROR = 'error';

    private const REQUEST_TYPE_DELETE = 'DELETE';
    private const REQUEST_TYPE_GET = 'GET';
    private const REQUEST_TYPE_PATCH = 'PATCH';
    private const REQUEST_TYPE_POST = 'POST';
    private const REQUEST_TYPE_PUT = 'PUT';

    private const HEADER_FOR_IDEMPOTENCY_KEY = 'Idempotency-Key';
    private const HEADER_FOR_REQUEST_ID = 'x-request-id';

    /**
     * Every documented error code, mapped to the exception it raises.
     *
     * Branch on the exception class, never on the message text: the wording is not part of
     * the API contract and may be reworded, the code is.
     */
    private const EXCEPTION_FOR_ERROR_CODE = [
        // specific
        'missing_api_key' => MissingApiKeyException::class,
        'invalid_api_key' => InvalidApiKeyException::class,
        'key_scope_pool' => KeyScopePoolException::class,
        'key_scope_mailbox' => KeyScopeMailboxException::class,
        'quota_exceeded' => QuotaExceededException::class,
        'invalid_from' => InvalidFromException::class,
        'conflicting_from_name' => ConflictingFromNameException::class,
        'no_recipients' => NoRecipientsException::class,
        'too_many_recipients' => TooManyRecipientsException::class,
        'body_too_large' => BodyTooLargeException::class,
        'idempotency_key_reuse' => IdempotencyKeyReuseException::class,
        'idempotency_in_progress' => IdempotencyInProgressException::class,
        'idempotency_key_invalid' => IdempotencyKeyInvalidException::class,
        // generic, derived from the HTTP status
        'bad_request' => BadRequestException::class,
        'unauthorized' => UnauthorizedException::class,
        'forbidden' => ForbiddenException::class,
        'not_found' => NotFoundException::class,
        'method_not_allowed' => MethodNotAllowedException::class,
        'conflict' => ConflictException::class,
        'payload_too_large' => PayloadTooLargeException::class,
        'unprocessable' => UnprocessableException::class,
        'rate_limited' => RateLimitedException::class,
        'server_error' => ServerErrorException::class,
    ];

    private string $apiKey;

    private string $apiUrl;

    private int $timeoutInSeconds;

    private bool $debug = false;

    private ?string $lastRequestId = null;

    /**
     * @param string $apiKey  a 2m_live_… key from Settings → API keys (or the admin panel)
     * @param string $apiUrl  override only for a staging environment
     * @param int    $timeoutInSeconds  raise it for deep address validation, which probes a remote SMTP server
     */
    public function __construct(
        string $apiKey,
        string $apiUrl = self::API_URL,
        int $timeoutInSeconds = 30
    ) {
        $this->apiKey = $apiKey;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->timeoutInSeconds = $timeoutInSeconds;
    }

    /**
     * Test validity for API-key.
     *
     * True for any working key, whatever its scope — a send-only mailbox key included,
     * since the service-info endpoint is reachable by all of them. False only when the key
     * itself is rejected; connection problems still throw, because "the network is down" is
     * not an answer to "is this key valid".
     */
    public function ping() : bool
    {
        try {
            $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_SERVICE_INFO);
        } catch (MissingApiKeyException | InvalidApiKeyException | UnauthorizedException $exception) {
            return false;
        }

        return true;
    }

    /**
     * What this key is allowed to reach: service, version, scope and endpoint list.
     */
    public function serviceInfo() : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_SERVICE_INFO);
    }

    // ---------------------------------------------------------------------------------
    // Sending
    // ---------------------------------------------------------------------------------

    /**
     * Send a message.
     *
     * The result is a 202: ACCEPTED, not delivered. The relay picks the message up a moment
     * later, so watch messageStatus(), events() or a webhook for the outcome.
     *
     * Pass an $idempotencyKey and the call becomes safe to retry: a replay returns the
     * ORIGINAL result instead of queueing a second message. Choose something stable per
     * logical message — an order id beats a random UUID, which changes on every retry and
     * therefore protects nothing. Reusing one key with a different message raises
     * IdempotencyKeyReuseException; replaying while the first call is still running raises
     * IdempotencyInProgressException. A failed call releases its key, so retrying after a
     * transient error is a fresh attempt rather than a replay of the failure.
     *
     * @throws TwoMailException
     */
    public function send(Message $message, ?string $idempotencyKey = null) : Result
    {
        $headers = [];

        if (null !== $idempotencyKey && '' !== $idempotencyKey) {
            $headers[] = self::HEADER_FOR_IDEMPOTENCY_KEY . ': ' . $idempotencyKey;
        }

        $response = $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_MESSAGES,
            $message->toArray(),
            $headers
        );

        return Result::fromArray($response);
    }

    /**
     * Send a message without building one first.
     *
     * Note that the sender address and the sender name are separate arguments: $fromEmail
     * carries the address, $fromName the display name. Use send() with a Message for
     * anything more — cc, bcc, reply-to, scheduling, test mode.
     *
     * @param string|string[] $to
     * @throws TwoMailException
     */
    public function sendMail(
        string $fromEmail,
        $to,
        string $subject,
        ?string $html = null,
        ?string $text = null,
        string $fromName = '',
        ?string $idempotencyKey = null
    ) : Result {
        $message = Message::create()
            ->from($fromEmail, $fromName)
            ->to($to)
            ->subject($subject);

        if (null !== $html) {
            $message->html($html);
        }

        if (null !== $text) {
            $message->text($text);
        }

        return $this->send($message, $idempotencyKey);
    }

    /**
     * Where a submitted message stands.
     *
     * @throws TwoMailException
     */
    public function messageStatus(string $messageId) : Status
    {
        return Status::fromArray(
            $this->doCall(
                self::REQUEST_TYPE_GET,
                self::API_ENDPOINT_FOR_MESSAGES . '/' . rawurlencode($messageId)
            )
        );
    }

    /**
     * Withdraw a message that has not gone out yet — typically one scheduled with
     * Message::sendAt().
     *
     * Only a queued message can be withdrawn. Once the worker has claimed it the mail is
     * already with the relay, and the API raises ConflictException rather than reporting a
     * cancellation it cannot honour. Cancelling removes the message, so a later
     * messageStatus() for it raises NotFoundException.
     *
     * @throws TwoMailException
     */
    public function cancelMessage(string $messageId) : bool
    {
        $response = $this->doCall(
            self::REQUEST_TYPE_DELETE,
            self::API_ENDPOINT_FOR_MESSAGES . '/' . rawurlencode($messageId)
        );

        return (bool) ($response['cancelled'] ?? false);
    }

    /**
     * Read the send pipeline: accepted, delivered, deferred, bounced, opened, rejected,
     * failed. The pull alternative to webhooks, for an application with no public endpoint.
     *
     * Defaults to the last 7 days. Filters: event, mailbox, recipient, begin, end, limit
     * (max 300). A mailbox key always sees only its own mailbox — asking for another one is
     * refused, not quietly ignored.
     *
     * @param array<string, string|int> $filters
     * @return Event[]
     * @throws TwoMailException
     */
    public function events(array $filters = []) : array
    {
        $response = $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_EVENTS, $filters);

        return Event::fromArrays($response['events'] ?? []);
    }

    // ---------------------------------------------------------------------------------
    // Mailboxes (sending domains)
    // ---------------------------------------------------------------------------------

    /**
     * Admin keys list every mailbox; a pool key lists only its own pool's.
     *
     * @throws TwoMailException
     */
    public function mailboxes() : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_MAILBOXES)['mailboxes'] ?? [];
    }

    /**
     * @throws TwoMailException
     */
    public function mailbox(int $mailboxId) : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId);
    }

    /**
     * Onboard a sending domain. Returns ['mailbox' => [...], 'credential' => [...]] — the
     * credential password is shown ONCE and cannot be retrieved later.
     *
     * Options: allowed_domains (CSV, defaults to the domain), monthly_limit, group_id,
     * smtp_enabled, comment, credential_label, credential_username. For a pool key both
     * group_id and monthly_limit are ignored: the mailbox joins that key's pool and the
     * pool's shared limit applies.
     *
     * @throws TwoMailException
     */
    public function createMailbox(
        string $domain,
        string $fromName,
        string $fromEmail,
        array $options = []
    ) : array {
        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_MAILBOXES,
            array_merge(
                $options,
                [
                    'domain' => $domain,
                    'from_name' => $fromName,
                    'from_email' => $fromEmail,
                ]
            )
        );
    }

    /**
     * This month's usage — pooled when the mailbox is in a group — with a per-credential
     * breakdown, so you can see which application is spending the limit.
     *
     * @throws TwoMailException
     */
    public function mailboxUsage(int $mailboxId) : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/usage');
    }

    /**
     * The SPF, DKIM and DMARC records the customer must publish, each with a verified flag.
     *
     * Worth surfacing in your own onboarding screen: mail from an unverified domain is
     * delivered, but far more of it lands in spam.
     *
     * @throws TwoMailException
     */
    public function mailboxDnsRecords(int $mailboxId) : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/dns');
    }

    /**
     * Move a mailbox into a pool, so it shares that pool's monthly limit. Admin keys only.
     *
     * @throws TwoMailException
     */
    public function assignMailboxToGroup(int $mailboxId, int $groupId) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_PUT,
            self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/group',
            ['group_id' => $groupId]
        );
    }

    /**
     * Take a mailbox out of its pool; its own monthly_limit applies again.
     *
     * @throws TwoMailException
     */
    public function removeMailboxFromGroup(int $mailboxId) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_DELETE,
            self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/group'
        );
    }

    // ---------------------------------------------------------------------------------
    // SMTP credentials
    // ---------------------------------------------------------------------------------

    /**
     * @throws TwoMailException
     */
    public function credentials(int $mailboxId) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_GET,
            self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/credentials'
        )['credentials'] ?? [];
    }

    /**
     * One SMTP login per application, so a leaked password can be revoked on its own and
     * usage can be attributed. The password is returned ONCE.
     *
     * @throws TwoMailException
     */
    public function createCredential(int $mailboxId, string $label) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/credentials',
            ['label' => $label]
        );
    }

    /**
     * Revoke an SMTP login. Immediate — anything still using it stops sending.
     *
     * @throws TwoMailException
     */
    public function deleteCredential(int $mailboxId, int $credentialId) : bool
    {
        $response = $this->doCall(
            self::REQUEST_TYPE_DELETE,
            self::API_ENDPOINT_FOR_MAILBOXES . '/' . $mailboxId . '/credentials/' . $credentialId
        );

        return (bool) ($response['deleted'] ?? false);
    }

    // ---------------------------------------------------------------------------------
    // Limit groups (pools)
    // ---------------------------------------------------------------------------------

    /**
     * @throws TwoMailException
     */
    public function groups() : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_GROUPS)['groups'] ?? [];
    }

    /**
     * A pool lets several mailboxes share one monthly limit. Admin keys only.
     *
     * @throws TwoMailException
     */
    public function createGroup(string $name, int $monthlyLimit) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_GROUPS,
            ['name' => $name, 'monthly_limit' => $monthlyLimit]
        );
    }

    /**
     * Logins that can open every mailbox in this pool. Admin keys only — a machine key must
     * not be able to hand out control-panel access.
     *
     * @throws TwoMailException
     */
    public function groupUsers(int $groupId) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_GET,
            self::API_ENDPOINT_FOR_GROUPS . '/' . $groupId . '/users'
        )['users'] ?? [];
    }

    /**
     * Give a login access to every mailbox in a pool, present and future. Identify the
     * account by user id or by email — one of the two is required. Idempotent: re-granting
     * updates the demo flag rather than failing.
     *
     * @throws TwoMailException
     */
    public function grantGroupAccess(
        int $groupId,
        ?int $userId = null,
        ?string $email = null,
        bool $demoMode = false
    ) : array {
        $data = ['demo_mode' => $demoMode];

        if (null !== $userId) {
            $data['user_id'] = $userId;
        }

        if (null !== $email) {
            $data['email'] = $email;
        }

        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_GROUPS . '/' . $groupId . '/users',
            $data
        );
    }

    /**
     * @throws TwoMailException
     */
    public function revokeGroupAccess(int $groupId, int $userId) : bool
    {
        $response = $this->doCall(
            self::REQUEST_TYPE_DELETE,
            self::API_ENDPOINT_FOR_GROUPS . '/' . $groupId . '/users/' . $userId
        );

        return (bool) ($response['ok'] ?? false);
    }

    // ---------------------------------------------------------------------------------
    // API keys
    // ---------------------------------------------------------------------------------

    /**
     * Admin keys see every key; a pool key sees only the send-only keys of its own pool.
     * The key value is never returned — only the prefix, to identify it.
     *
     * @throws TwoMailException
     */
    public function apiKeys() : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_KEYS)['keys'] ?? [];
    }

    /**
     * @throws TwoMailException
     */
    public function apiKey(int $keyId) : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_KEYS . '/' . $keyId);
    }

    /**
     * Mint a send-only key for one mailbox: it can send, check status, cancel and read its
     * own events, and nothing else. This is what belongs in an application.
     *
     * The full key is in the 'key' field of the response and is shown ONCE.
     *
     * @throws TwoMailException
     */
    public function createMailboxApiKey(int $mailboxId, string $label) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_KEYS,
            ['scope' => 'mailbox', 'mailbox_id' => $mailboxId, 'label' => $label]
        );
    }

    /**
     * Mint a key scoped to one pool — for a reseller who manages their own mailboxes but
     * must stay inside their limit group. Admin keys only.
     *
     * A key can never hand out more authority than it holds, so there is no method to
     * create an admin key: that stays a control-panel action.
     *
     * @throws TwoMailException
     */
    public function createPoolApiKey(int $groupId, string $label) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_KEYS,
            ['scope' => 'pool', 'group_id' => $groupId, 'label' => $label]
        );
    }

    /**
     * Pause or resume a key without revoking it. A key cannot disable itself.
     *
     * @throws TwoMailException
     */
    public function enableApiKey(int $keyId, bool $enabled) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_PATCH,
            self::API_ENDPOINT_FOR_KEYS . '/' . $keyId,
            ['enabled' => $enabled]
        );
    }

    /**
     * Revoke a key. Immediate and irreversible; a key cannot delete itself.
     *
     * @throws TwoMailException
     */
    public function deleteApiKey(int $keyId) : bool
    {
        $response = $this->doCall(self::REQUEST_TYPE_DELETE, self::API_ENDPOINT_FOR_KEYS . '/' . $keyId);

        return (bool) ($response['ok'] ?? false);
    }

    // ---------------------------------------------------------------------------------
    // Webhooks
    // ---------------------------------------------------------------------------------

    /**
     * @throws TwoMailException
     */
    public function webhooks() : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_WEBHOOKS)['webhooks'] ?? [];
    }

    /**
     * @throws TwoMailException
     */
    public function webhook(int $webhookId) : array
    {
        return $this->doCall(self::REQUEST_TYPE_GET, self::API_ENDPOINT_FOR_WEBHOOKS . '/' . $webhookId);
    }

    /**
     * Register a URL to receive events. Leave $events empty for all of them, or pass a
     * subset of the EventType constants.
     *
     * The 'secret' in the response is shown ONCE — store it, then verify every incoming
     * delivery with WebhookSignature. $groupId is required for an admin key and ignored for
     * a pool key, which can only subscribe its own pool.
     *
     * @param string[] $events
     * @throws TwoMailException
     */
    public function createWebhook(string $url, array $events = [], ?int $groupId = null) : array
    {
        $data = ['url' => $url, 'events' => $events];

        if (null !== $groupId) {
            $data['group_id'] = $groupId;
        }

        return $this->doCall(self::REQUEST_TYPE_POST, self::API_ENDPOINT_FOR_WEBHOOKS, $data);
    }

    /**
     * Partial update — omitted fields keep their value. Accepts url, events, enabled and
     * rotate_secret. Use enabled to pause deliveries without losing the subscription; the
     * pool a webhook belongs to cannot be changed.
     *
     * @throws TwoMailException
     */
    public function updateWebhook(int $webhookId, array $changes) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_PATCH,
            self::API_ENDPOINT_FOR_WEBHOOKS . '/' . $webhookId,
            $changes
        );
    }

    /**
     * Issue a new signing secret, returned ONCE in the response.
     *
     * The old secret stops verifying immediately, so deploy the new one first: any delivery
     * arriving in between will fail verification. Retries mean nothing is lost, but your
     * endpoint will reject those attempts until the new secret is live.
     *
     * @throws TwoMailException
     */
    public function rotateWebhookSecret(int $webhookId) : array
    {
        return $this->updateWebhook($webhookId, ['rotate_secret' => true]);
    }

    /**
     * @throws TwoMailException
     */
    public function deleteWebhook(int $webhookId) : bool
    {
        $response = $this->doCall(
            self::REQUEST_TYPE_DELETE,
            self::API_ENDPOINT_FOR_WEBHOOKS . '/' . $webhookId
        );

        return (bool) ($response['ok'] ?? false);
    }

    // ---------------------------------------------------------------------------------
    // Address validation
    // ---------------------------------------------------------------------------------

    /**
     * Check an address before you send to it: syntax, MX, disposable and role flags, and a
     * typo suggestion in did_you_mean.
     *
     * $deep adds an SMTP probe of the receiving server. It is slower and can be
     * inconclusive when port 25 is blocked, which is why the result may be 'unknown' rather
     * than a straight yes or no.
     *
     * @throws TwoMailException
     */
    public function validateEmail(string $email, bool $deep = false) : array
    {
        return $this->doCall(
            self::REQUEST_TYPE_POST,
            self::API_ENDPOINT_FOR_VALIDATION,
            ['email' => $email, 'deep' => $deep]
        );
    }

    /**
     * Is this address worth sending to?
     *
     * True only for a 'deliverable' verdict. 'risky' and 'unknown' both return false, so a
     * signup form using this will turn away some valid addresses — read validateEmail()
     * yourself if you would rather decide per category.
     *
     * @throws TwoMailException
     */
    public function isValidEmail(string $email, bool $deep = false) : bool
    {
        return 'deliverable' === ($this->validateEmail($email, $deep)['result'] ?? '');
    }

    // ---------------------------------------------------------------------------------
    // Plumbing
    // ---------------------------------------------------------------------------------

    /**
     * The X-Request-Id of the last call. Quote it in a support request and the exact call
     * can be found in the 2mail activity log.
     */
    public function lastRequestId() : ?string
    {
        return $this->lastRequestId;
    }

    /**
     * Echo every request and response. Never leave this on in production — the output
     * includes message bodies and recipient addresses.
     */
    public function enableDebugging() : void
    {
        $this->debug = true;
    }

    /**
     * The single place every call goes through.
     *
     * Private, and the class is final: to exercise your own code against the API without
     * emailing anyone, use the server-side test mode (Message::test()) rather than stubbing
     * the transport. It runs the real authorization and validation, which a local stub
     * cannot.
     *
     * @param string[] $extraHeaders
     * @throws InvalidHttpResponseCodeException
     * @throws TransportException
     * @throws TwoMailException
     */
    private function doCall(
        string $requestType,
        string $endPoint,
        array $data = [],
        array $extraHeaders = []
    ) : array {
        // The trailing slash on the bare base URL is not cosmetic: without it the web
        // server sees a directory and answers 301 to the slashed form, which this client
        // deliberately does not follow.
        $url = sprintf('%1$s/%2$s', $this->apiUrl, $endPoint);

        if (self::REQUEST_TYPE_GET === $requestType && [] !== $data) {
            $url .= '?' . http_build_query($data);
        }

        $headers = array_merge(
            [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ],
            $extraHeaders
        );

        $body = null;

        if (in_array($requestType, [self::REQUEST_TYPE_POST, self::REQUEST_TYPE_PUT, self::REQUEST_TYPE_PATCH], true)) {
            $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }

        $this->lastRequestId = null;
        $responseHeaders = [];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $requestType);

        // Redirects are NOT followed. cURL would replay the Authorization header on the
        // redirect target, handing the API key to whatever host the Location points at.
        // A misconfigured base URL should be a loud error, not a silent credential leak.
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeoutInSeconds);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt(
            $curl,
            CURLOPT_HEADERFUNCTION,
            static function ($resource, string $header) use (&$responseHeaders) : int {
                $pair = explode(':', $header, 2);

                if (2 === count($pair)) {
                    $responseHeaders[strtolower(trim($pair[0]))] = trim($pair[1]);
                }

                return strlen($header);
            }
        );

        if (null !== $body) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($curl);
        $httpResponseCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        $this->lastRequestId = $responseHeaders[self::HEADER_FOR_REQUEST_ID] ?? null;

        if (true === $this->debug) {
            echo $requestType . ' ' . $url . PHP_EOL;

            if (null !== $body) {
                echo $body . PHP_EOL;
            }

            echo $httpResponseCode . ' ' . (is_string($rawResponse) ? $rawResponse : '') . PHP_EOL . PHP_EOL;
        }

        if (false === $rawResponse || 0 !== $curlErrorNumber) {
            throw new TransportException(
                sprintf('Could not reach the 2mail API at "%1$s": %2$s', $url, $curlError),
                $curlErrorNumber
            );
        }

        if ($httpResponseCode >= 300 && $httpResponseCode < 400) {
            throw new InvalidHttpResponseCodeException(
                sprintf(
                    'The 2mail API redirected to "%1$s". Redirects are not followed, because that would send your API key to another host. Point the base URL straight at the API — the default is "%2$s".',
                    $responseHeaders['location'] ?? 'an unknown location',
                    self::API_URL
                ),
                $httpResponseCode
            );
        }

        $response = json_decode($rawResponse, true);

        if (! is_array($response)) {
            throw new InvalidHttpResponseCodeException(
                sprintf(
                    'The 2mail API answered HTTP %1$d but not with JSON. Usually a wrong base URL, or a firewall answering instead of the API. First bytes: "%2$s"',
                    $httpResponseCode,
                    substr(trim((string) $rawResponse), 0, 200)
                ),
                $httpResponseCode
            );
        }

        if ($httpResponseCode >= 400) {
            $this->throwExceptionForError($response, $httpResponseCode);
        }

        return $response;
    }

    /**
     * @throws InvalidResponseCodeException
     * @throws TwoMailException
     */
    private function throwExceptionForError(array $response, int $httpResponseCode) : void
    {
        $code = (string) ($response[self::KEY_FOR_CODE] ?? '');
        $message = (string) ($response[self::KEY_FOR_ERROR] ?? 'The 2mail API refused the call.');

        if (null !== $this->lastRequestId) {
            $message .= sprintf(' (request id: %1$s)', $this->lastRequestId);
        }

        if (! array_key_exists($code, self::EXCEPTION_FOR_ERROR_CODE)) {
            throw new InvalidResponseCodeException(
                sprintf(
                    'The given error code "%1$s" is currently not supported in "%3$s" with given response message "%2$s". Please contact a developer or create a merge request to add it.',
                    $code,
                    $message,
                    self::class
                ),
                $httpResponseCode
            );
        }

        /** @var class-string<TwoMailException> $exceptionClass */
        $exceptionClass = self::EXCEPTION_FOR_ERROR_CODE[$code];

        throw new $exceptionClass($message, $httpResponseCode);
    }
}
