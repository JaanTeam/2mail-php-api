<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\SentMessage;

/**
 * What came back from a send.
 *
 * A send returns 202 = ACCEPTED, not delivered: the relay picks the message up a moment
 * later, so the outcome arrives through TwoMail::messageStatus(), a webhook, or
 * TwoMail::events() — never from this object.
 *
 * A test send (Message::test()) returns the same object with isTest() true and no id:
 * nothing was queued, so there is nothing to look up afterwards.
 */
final class Result
{
    public const KEY_FOR_ID = 'id';
    public const KEY_FOR_STATUS = 'status';
    public const KEY_FOR_MAILBOX_ID = 'mailbox_id';
    public const KEY_FOR_SCHEDULED_FOR = 'scheduled_for';
    public const KEY_FOR_TEST = 'test';
    public const KEY_FOR_NOTE = 'note';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_VALIDATED = 'validated';

    private function __construct(
        private readonly ?string $id,
        private readonly string $status,
        private readonly int $mailboxId,
        private readonly ?string $scheduledFor,
        private readonly bool $test,
        private readonly ?string $note
    ) {
    }

    public static function fromArray(array $array) : self
    {
        return new self(
            isset($array[self::KEY_FOR_ID]) ? (string) $array[self::KEY_FOR_ID] : null,
            (string) ($array[self::KEY_FOR_STATUS] ?? ''),
            (int) ($array[self::KEY_FOR_MAILBOX_ID] ?? 0),
            $array[self::KEY_FOR_SCHEDULED_FOR] ?? null,
            (bool) ($array[self::KEY_FOR_TEST] ?? false),
            $array[self::KEY_FOR_NOTE] ?? null
        );
    }

    /**
     * The message id to poll with TwoMail::messageStatus(), or null after a test send.
     */
    public function id() : ?string
    {
        return $this->id;
    }

    public function status() : string
    {
        return $this->status;
    }

    public function mailboxId() : int
    {
        return $this->mailboxId;
    }

    /**
     * Delivery time when the message was scheduled, null when it goes out as soon as
     * possible.
     */
    public function scheduledFor() : ?string
    {
        return $this->scheduledFor;
    }

    public function isScheduled() : bool
    {
        return null !== $this->scheduledFor;
    }

    /**
     * True when this was a test send: validated only, nothing queued, nothing sent.
     */
    public function isTest() : bool
    {
        return $this->test;
    }

    /**
     * Explanation returned with a test send; null for a real one.
     */
    public function note() : ?string
    {
        return $this->note;
    }
}
