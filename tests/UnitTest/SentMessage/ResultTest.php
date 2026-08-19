<?php

declare(strict_types=1);

namespace UnitTest\SentMessage;

use JaanBV\TwoMail\SentMessage\Result;
use JaanBV\TwoMail\SentMessage\Status;
use PHPUnit\Framework\TestCase;

final class ResultTest extends TestCase
{
    public function testItReadsAQueuedSend() : void
    {
        $result = Result::fromArray([
            'id' => '4f9ae1',
            'status' => 'queued',
            'mailbox_id' => 42,
            'scheduled_for' => null,
        ]);

        self::assertSame('4f9ae1', $result->id());
        self::assertSame(Result::STATUS_QUEUED, $result->status());
        self::assertSame(42, $result->mailboxId());
        self::assertFalse($result->isScheduled());
        self::assertFalse($result->isTest());
    }

    public function testItReadsAScheduledSend() : void
    {
        $result = Result::fromArray([
            'id' => '4f9ae1',
            'status' => 'scheduled',
            'mailbox_id' => 42,
            'scheduled_for' => '2026-08-20 09:00:00',
        ]);

        self::assertTrue($result->isScheduled());
        self::assertSame('2026-08-20 09:00:00', $result->scheduledFor());
    }

    /**
     * A test send stores nothing, so it has no id to look up afterwards. Callers must be
     * able to see that rather than be handed an empty string that looks like one.
     */
    public function testATestSendHasNoId() : void
    {
        $result = Result::fromArray([
            'test' => true,
            'status' => 'validated',
            'mailbox_id' => 42,
            'from' => 'no-reply@acme.com',
            'to' => ['customer@example.com'],
            'scheduled_for' => null,
            'note' => 'Validated only — no message was queued and nothing was sent.',
        ]);

        self::assertNull($result->id());
        self::assertTrue($result->isTest());
        self::assertSame(Result::STATUS_VALIDATED, $result->status());
        self::assertNotNull($result->note());
    }

    public function testItReadsADeliveryStatus() : void
    {
        $status = Status::fromArray([
            'id' => '4f9ae1',
            'status' => 'sent',
            'to' => 'customer@example.com',
            'mailbox_id' => 42,
            'message_id' => '<abc@acme.com>',
            'error' => null,
            'created_at' => '2026-08-19 10:00:00',
            'sent_at' => '2026-08-19 10:00:03',
        ]);

        self::assertTrue($status->isSent());
        self::assertTrue($status->isFinished());
        self::assertFalse($status->isQueued());
        self::assertSame('<abc@acme.com>', $status->messageId());
    }

    public function testAQueuedMessageIsNotFinished() : void
    {
        $status = Status::fromArray(['id' => '4f9ae1', 'status' => 'queued', 'mailbox_id' => 42]);

        self::assertTrue($status->isQueued());
        self::assertFalse($status->isFinished());
    }

    public function testAFailedMessageIsFinished() : void
    {
        $status = Status::fromArray([
            'id' => '4f9ae1',
            'status' => 'failed',
            'mailbox_id' => 42,
            'error' => '550 unknown user',
        ]);

        self::assertTrue($status->isFailed());
        self::assertTrue($status->isFinished());
        self::assertSame('550 unknown user', $status->error());
    }
}
