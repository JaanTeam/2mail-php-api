<?php

declare(strict_types=1);

namespace JaanBV\TwoMail;

use JaanBV\TwoMail\Event\Event;
use JaanBV\TwoMail\Exception\InvalidWebhookSignatureException;

/**
 * Verify that an incoming webhook really came from 2mail.
 *
 * Your webhook URL is public: anyone who learns it can POST to it. The signature is what
 * separates a real event from someone else's invention, so verify BEFORE acting on the
 * payload — before marking an address as bounced, before cancelling an account.
 *
 * 2mail signs each delivery with the secret shown once when the webhook was created:
 *
 *     X-2mail-Signature: t=<unix timestamp>,v1=<hex hmac>
 *     v1 = HMAC-SHA256(secret, "<t>.<raw request body>")
 *
 * Sign the RAW body, byte for byte. Decoding the JSON and re-encoding it changes spacing
 * and escaping, and the signature will never match.
 *
 *     $payload = file_get_contents('php://input');
 *
 *     try {
 *         $event = WebhookSignature::event(
 *             $secret,
 *             $_SERVER['HTTP_X_2MAIL_SIGNATURE'] ?? '',
 *             $payload
 *         );
 *     } catch (InvalidWebhookSignatureException $exception) {
 *         http_response_code(403);
 *         exit;
 *     }
 *
 * Answer 2xx once you have stored the event. Anything else is retried with backoff, so a
 * slow handler that does its work first and answers later will receive duplicates — accept
 * the event quickly, process it afterwards.
 */
final class WebhookSignature
{
    public const HEADER = 'X-2mail-Signature';

    /**
     * How far the timestamp may be from now, in seconds.
     *
     * This is what stops a replay: without it, an attacker who once captured a valid
     * request could resend it verbatim forever, signature and all.
     */
    public const DEFAULT_TOLERANCE_IN_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * Is this delivery genuine and recent?
     */
    public static function isValid(
        string $secret,
        string $signatureHeader,
        string $rawBody,
        int $toleranceInSeconds = self::DEFAULT_TOLERANCE_IN_SECONDS
    ) : bool {
        try {
            self::assertValid($secret, $signatureHeader, $rawBody, $toleranceInSeconds);
        } catch (InvalidWebhookSignatureException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Same check, but says what was wrong — useful while building the endpoint.
     *
     * @throws InvalidWebhookSignatureException
     */
    public static function assertValid(
        string $secret,
        string $signatureHeader,
        string $rawBody,
        int $toleranceInSeconds = self::DEFAULT_TOLERANCE_IN_SECONDS
    ) : void {
        if ('' === $secret) {
            throw new InvalidWebhookSignatureException(
                'No signing secret given. It is shown once when the webhook is created; rotate it with TwoMail::rotateWebhookSecret() if it was lost.'
            );
        }

        $parts = self::parse($signatureHeader);

        if (null === $parts) {
            throw new InvalidWebhookSignatureException(
                sprintf('Malformed %1$s header: "%2$s". Expected "t=<timestamp>,v1=<hex>".', self::HEADER, $signatureHeader)
            );
        }

        [$timestamp, $signature] = $parts;

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        // hash_equals, not ===: a plain comparison returns as soon as two bytes differ, and
        // that timing difference is enough to guess a signature byte by byte.
        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException(
                'Signature does not match. Verify the RAW request body — re-encoded JSON will never match.'
            );
        }

        if ($toleranceInSeconds > 0 && abs(time() - (int) $timestamp) > $toleranceInSeconds) {
            throw new InvalidWebhookSignatureException(
                sprintf(
                    'Signature is valid but its timestamp is more than %1$d seconds off. Replayed request, or a clock that needs setting.',
                    $toleranceInSeconds
                )
            );
        }
    }

    /**
     * Verify and decode in one step: returns the event, or throws.
     *
     * @throws InvalidWebhookSignatureException
     */
    public static function event(
        string $secret,
        string $signatureHeader,
        string $rawBody,
        int $toleranceInSeconds = self::DEFAULT_TOLERANCE_IN_SECONDS
    ) : Event {
        self::assertValid($secret, $signatureHeader, $rawBody, $toleranceInSeconds);

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new InvalidWebhookSignatureException('Signature is valid but the body is not JSON.');
        }

        return Event::fromArray($payload);
    }

    /**
     * @return array{0: string, 1: string}|null the timestamp and the v1 signature
     */
    private static function parse(string $header) : ?array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (2 !== count($pair)) {
                continue;
            }

            if ('t' === $pair[0] && ctype_digit($pair[1])) {
                $timestamp = $pair[1];
            }

            if ('v1' === $pair[0] && ctype_xdigit($pair[1])) {
                $signature = $pair[1];
            }
        }

        if (null === $timestamp || null === $signature) {
            return null;
        }

        return [$timestamp, $signature];
    }
}
