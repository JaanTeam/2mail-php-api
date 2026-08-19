<?php

declare(strict_types=1);

namespace UnitTest;

use JaanBV\TwoMail\Event\EventType;
use JaanBV\TwoMail\Exception\InvalidWebhookSignatureException;
use JaanBV\TwoMail\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_0123456789abcdef';

    public function testItAcceptsAGenuineDelivery() : void
    {
        $body = '{"event":"bounced","recipient":"user@example.com"}';

        self::assertTrue(
            WebhookSignature::isValid(self::SECRET, $this->sign($body), $body)
        );
    }

    public function testItRejectsATamperedBody() : void
    {
        $header = $this->sign('{"event":"bounced","recipient":"user@example.com"}');

        self::assertFalse(
            WebhookSignature::isValid(self::SECRET, $header, '{"event":"delivered","recipient":"user@example.com"}')
        );
    }

    public function testItRejectsAnotherSecret() : void
    {
        $body = '{"event":"bounced"}';

        self::assertFalse(
            WebhookSignature::isValid('whsec_somethingelse', $this->sign($body), $body)
        );
    }

    /**
     * Without the timestamp check, anyone who once captured a valid delivery could resend
     * it verbatim forever — signature and all.
     */
    public function testItRejectsAReplayedDelivery() : void
    {
        $body = '{"event":"bounced"}';
        $header = $this->sign($body, time() - 3600);

        self::assertFalse(WebhookSignature::isValid(self::SECRET, $header, $body));

        // The signature itself is genuine; only its age disqualifies it.
        self::assertTrue(WebhookSignature::isValid(self::SECRET, $header, $body, 0));
    }

    public function testItRejectsAMalformedHeader() : void
    {
        foreach (['', 'nonsense', 't=123', 'v1=abc', 't=abc,v1=def', 't=123,v1=zzz'] as $header) {
            self::assertFalse(
                WebhookSignature::isValid(self::SECRET, $header, '{}'),
                sprintf('Header "%1$s" should not verify', $header)
            );
        }
    }

    public function testItRefusesToVerifyWithoutASecret() : void
    {
        $this->expectException(InvalidWebhookSignatureException::class);

        WebhookSignature::assertValid('', $this->sign('{}'), '{}');
    }

    public function testItDecodesTheEventAfterVerifying() : void
    {
        $body = json_encode([
            'event' => 'bounced',
            'timestamp' => '2026-08-19 10:00:00',
            'recipient' => 'user@example.com',
            'mailbox_id' => 42,
            'message_id' => '<abc@acme.com>',
            'detail' => '550 unknown user',
            'code' => '550',
        ]);

        $event = WebhookSignature::event(self::SECRET, $this->sign($body), $body);

        self::assertSame(EventType::BOUNCED, $event->event());
        self::assertSame('user@example.com', $event->recipient());
        self::assertSame(42, $event->mailboxId());
        self::assertSame('550', $event->code());
        self::assertTrue($event->isBounce());
        self::assertTrue($event->isFailure());
    }

    public function testItRefusesANonJsonBodyEvenWhenSigned() : void
    {
        $this->expectException(InvalidWebhookSignatureException::class);

        WebhookSignature::event(self::SECRET, $this->sign('not json'), 'not json');
    }

    private function sign(string $body, ?int $timestamp = null) : string
    {
        $timestamp = $timestamp ?? time();

        return sprintf(
            't=%1$d,v1=%2$s',
            $timestamp,
            hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET)
        );
    }
}
