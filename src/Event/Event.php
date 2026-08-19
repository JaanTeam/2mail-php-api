<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Event;

/**
 * One entry from the send pipeline: what happened, to whom, and when.
 *
 * The same shape arrives in a webhook payload, so a handler written against this object
 * works for both the pull (TwoMail::events()) and the push (webhook) route — see
 * Event::fromArray().
 */
final class Event
{
    public const KEY_FOR_EVENT = 'event';
    public const KEY_FOR_TIMESTAMP = 'timestamp';
    public const KEY_FOR_RECIPIENT = 'recipient';
    public const KEY_FOR_MAILBOX_ID = 'mailbox_id';
    public const KEY_FOR_MESSAGE_ID = 'message_id';
    public const KEY_FOR_DETAIL = 'detail';
    public const KEY_FOR_CODE = 'code';

    private function __construct(
        private readonly string $event,
        private readonly ?string $timestamp,
        private readonly ?string $recipient,
        private readonly int $mailboxId,
        private readonly ?string $messageId,
        private readonly ?string $detail,
        private readonly ?string $code
    ) {
    }

    public static function fromArray(array $array) : self
    {
        return new self(
            (string) ($array[self::KEY_FOR_EVENT] ?? ''),
            $array[self::KEY_FOR_TIMESTAMP] ?? null,
            $array[self::KEY_FOR_RECIPIENT] ?? null,
            (int) ($array[self::KEY_FOR_MAILBOX_ID] ?? 0),
            $array[self::KEY_FOR_MESSAGE_ID] ?? null,
            $array[self::KEY_FOR_DETAIL] ?? null,
            isset($array[self::KEY_FOR_CODE]) ? (string) $array[self::KEY_FOR_CODE] : null
        );
    }

    /**
     * @param array[] $arrays
     * @return self[]
     */
    public static function fromArrays(array $arrays) : array
    {
        return array_map(
            static fn (array $array) : self => self::fromArray($array),
            $arrays
        );
    }

    /**
     * One of the EventType constants.
     */
    public function event() : string
    {
        return $this->event;
    }

    public function timestamp() : ?string
    {
        return $this->timestamp;
    }

    public function recipient() : ?string
    {
        return $this->recipient;
    }

    public function mailboxId() : int
    {
        return $this->mailboxId;
    }

    /**
     * The RFC Message-ID, when the event can be tied to a specific message. Matches
     * Status::messageId().
     */
    public function messageId() : ?string
    {
        return $this->messageId;
    }

    /**
     * Human-readable explanation — the remote server's response for a bounce, for example.
     */
    public function detail() : ?string
    {
        return $this->detail;
    }

    /**
     * SMTP status code where the receiving server gave one.
     */
    public function code() : ?string
    {
        return $this->code;
    }

    /**
     * The address is refusing mail. A hard bounce is worth acting on: stop sending to it.
     */
    public function isBounce() : bool
    {
        return EventType::BOUNCED === $this->event;
    }

    /**
     * Did not get through, and will not be retried.
     */
    public function isFailure() : bool
    {
        return in_array($this->event, [EventType::BOUNCED, EventType::FAILED, EventType::REJECTED], true);
    }
}
