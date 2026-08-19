<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\SentMessage;

/**
 * Where a submitted message currently stands.
 *
 * "sent" means the relay accepted and handed it on — the receiving server may still bounce
 * it afterwards. A bounce is an event, not a status: watch for it with TwoMail::events() or
 * a webhook.
 */
final class Status
{
    public const KEY_FOR_ID = 'id';
    public const KEY_FOR_STATUS = 'status';
    public const KEY_FOR_TO = 'to';
    public const KEY_FOR_MAILBOX_ID = 'mailbox_id';
    public const KEY_FOR_MESSAGE_ID = 'message_id';
    public const KEY_FOR_ERROR = 'error';
    public const KEY_FOR_CREATED_AT = 'created_at';
    public const KEY_FOR_SENT_AT = 'sent_at';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    private function __construct(
        private readonly string $id,
        private readonly string $status,
        private readonly string $to,
        private readonly int $mailboxId,
        private readonly ?string $messageId,
        private readonly ?string $error,
        private readonly ?string $createdAt,
        private readonly ?string $sentAt
    ) {
    }

    public static function fromArray(array $array) : self
    {
        return new self(
            (string) ($array[self::KEY_FOR_ID] ?? ''),
            (string) ($array[self::KEY_FOR_STATUS] ?? ''),
            (string) ($array[self::KEY_FOR_TO] ?? ''),
            (int) ($array[self::KEY_FOR_MAILBOX_ID] ?? 0),
            $array[self::KEY_FOR_MESSAGE_ID] ?? null,
            $array[self::KEY_FOR_ERROR] ?? null,
            $array[self::KEY_FOR_CREATED_AT] ?? null,
            $array[self::KEY_FOR_SENT_AT] ?? null
        );
    }

    public function id() : string
    {
        return $this->id;
    }

    public function status() : string
    {
        return $this->status;
    }

    public function to() : string
    {
        return $this->to;
    }

    public function mailboxId() : int
    {
        return $this->mailboxId;
    }

    /**
     * The RFC Message-ID the relay stamped on the mail, once it has gone out. Use it to
     * tie a later bounce or open event back to this message.
     */
    public function messageId() : ?string
    {
        return $this->messageId;
    }

    /**
     * Why it failed, when it did.
     */
    public function error() : ?string
    {
        return $this->error;
    }

    public function createdAt() : ?string
    {
        return $this->createdAt;
    }

    public function sentAt() : ?string
    {
        return $this->sentAt;
    }

    /**
     * Still waiting to be picked up — the only state in which it can still be cancelled.
     */
    public function isQueued() : bool
    {
        return self::STATUS_QUEUED === $this->status;
    }

    public function isSending() : bool
    {
        return self::STATUS_SENDING === $this->status;
    }

    /**
     * Handed to the relay. Not proof of delivery — a bounce can still follow.
     */
    public function isSent() : bool
    {
        return self::STATUS_SENT === $this->status;
    }

    public function isFailed() : bool
    {
        return self::STATUS_FAILED === $this->status;
    }

    /**
     * Nothing more will happen to this message: it either went out or failed. Stop polling
     * when this is true.
     *
     * There is no "cancelled" status to look for: cancelling removes the message outright,
     * so a later status call for it raises NotFoundException.
     */
    public function isFinished() : bool
    {
        return $this->isSent() || $this->isFailed();
    }
}
