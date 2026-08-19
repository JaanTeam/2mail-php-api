<?php

declare(strict_types=1);

namespace UnitTest;

use DateTimeImmutable;
use JaanBV\TwoMail\Exception\MessageIncompleteException;
use JaanBV\TwoMail\Message;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testItBuildsTheMinimalPayload() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();

        self::assertSame(
            [
                'from' => 'no-reply@acme.com',
                'to' => ['customer@example.com'],
                'subject' => 'Your order',
                'text' => 'Thanks!',
            ],
            $data
        );
    }

    /**
     * The whole point of from($email, $name): the address and the display name land in
     * separate fields, so the combined "Acme <no-reply@acme.com>" spelling — which the API
     * refuses when it contradicts from_name — cannot be produced by accident.
     */
    public function testItKeepsTheSenderNameOutOfTheFromAddress() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com', 'Acme')
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();

        self::assertSame('no-reply@acme.com', $data['from']);
        self::assertSame('Acme', $data['from_name']);
    }

    public function testItOmitsAnEmptySenderName() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();

        self::assertArrayNotHasKey('from_name', $data);
    }

    public function testItCollectsRecipientsFromRepeatedCallsAndArrays() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com')
            ->to('one@example.com')
            ->to(['two@example.com', 'three@example.com'])
            ->cc('manager@example.com')
            ->bcc('archive@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();

        self::assertSame(['one@example.com', 'two@example.com', 'three@example.com'], $data['to']);
        self::assertSame(['manager@example.com'], $data['cc']);
        self::assertSame(['archive@example.com'], $data['bcc']);
    }

    public function testItSendsBooleansForTrackingAndTest() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->tracking(false)
            ->test()
            ->toArray();

        // false must survive: it forces tracking OFF, which is not the same as omitting it
        // and inheriting the mailbox default.
        self::assertFalse($data['tracking']);
        self::assertTrue($data['test']);
    }

    public function testItOmitsTrackingWhenItWasNeverSet() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();

        self::assertArrayNotHasKey('tracking', $data);
        self::assertArrayNotHasKey('test', $data);
    }

    public function testItFormatsTheScheduledTimeAsIso8601() : void
    {
        $data = Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->sendAt(new DateTimeImmutable('2026-08-20T09:00:00+00:00'))
            ->toArray();

        self::assertSame('2026-08-20T09:00:00+00:00', $data['send_at']);
    }

    public function testItRefusesAMessageWithoutASender() : void
    {
        $this->expectException(MessageIncompleteException::class);

        Message::create()
            ->to('customer@example.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();
    }

    public function testItRefusesAMessageWithoutARecipient() : void
    {
        $this->expectException(MessageIncompleteException::class);

        Message::create()
            ->from('no-reply@acme.com')
            ->subject('Your order')
            ->text('Thanks!')
            ->toArray();
    }

    public function testItRefusesAMessageWithoutASubject() : void
    {
        $this->expectException(MessageIncompleteException::class);

        Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->text('Thanks!')
            ->toArray();
    }

    public function testItRefusesAMessageWithoutABody() : void
    {
        $this->expectException(MessageIncompleteException::class);

        Message::create()
            ->from('no-reply@acme.com')
            ->to('customer@example.com')
            ->subject('Your order')
            ->toArray();
    }

    /**
     * Caught locally rather than by the API, so a bulk loop fails on the spot instead of
     * after a wasted round-trip.
     */
    public function testItRefusesMoreThanFiftyRecipients() : void
    {
        $message = Message::create()
            ->from('no-reply@acme.com')
            ->subject('Your order')
            ->text('Thanks!');

        for ($i = 0; $i < 49; $i++) {
            $message->to(sprintf('user%1$d@example.com', $i));
        }

        $message->cc('manager@example.com');

        // 50 exactly is still fine.
        self::assertCount(49, $message->toArray()['to']);

        $message->bcc('archive@example.com');

        $this->expectException(MessageIncompleteException::class);

        $message->toArray();
    }
}
