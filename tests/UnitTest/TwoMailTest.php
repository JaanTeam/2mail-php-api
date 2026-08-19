<?php

declare(strict_types=1);

namespace UnitTest;

use JaanBV\TwoMail\Exception\ConflictingFromNameException;
use JaanBV\TwoMail\Exception\ForbiddenException;
use JaanBV\TwoMail\Exception\IdempotencyInProgressException;
use JaanBV\TwoMail\Exception\IdempotencyKeyReuseException;
use JaanBV\TwoMail\Exception\InvalidApiKeyException;
use JaanBV\TwoMail\Exception\InvalidResponseCodeException;
use JaanBV\TwoMail\Exception\KeyScopeMailboxException;
use JaanBV\TwoMail\Exception\NotFoundException;
use JaanBV\TwoMail\Exception\QuotaExceededException;
use JaanBV\TwoMail\Exception\TwoMailException;
use JaanBV\TwoMail\TwoMail;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * These tests never touch the network: they drive the error mapping directly, which is the
 * part a caller actually depends on. Whether a given endpoint answers correctly is the
 * API's own business, and testing that from here would only produce a suite that fails
 * whenever the internet does.
 */
final class TwoMailTest extends TestCase
{
    /**
     * @dataProvider errorCodeProvider
     */
    public function testItMapsEveryDocumentedErrorCodeToItsOwnException(
        string $code,
        int $httpStatus,
        string $expectedException
    ) : void {
        $this->expectException($expectedException);

        $this->throwFor(['error' => 'Something was refused.', 'code' => $code], $httpStatus);
    }

    public function errorCodeProvider() : array
    {
        return [
            'invalid key' => ['invalid_api_key', 401, InvalidApiKeyException::class],
            'send-only key' => ['key_scope_mailbox', 403, KeyScopeMailboxException::class],
            'limit reached' => ['quota_exceeded', 403, QuotaExceededException::class],
            'from conflict' => ['conflicting_from_name', 400, ConflictingFromNameException::class],
            'idempotency reuse' => ['idempotency_key_reuse', 422, IdempotencyKeyReuseException::class],
            'idempotency running' => ['idempotency_in_progress', 409, IdempotencyInProgressException::class],
            'generic forbidden' => ['forbidden', 403, ForbiddenException::class],
            'generic not found' => ['not_found', 404, NotFoundException::class],
        ];
    }

    /**
     * Every exception this library raises implements the marker interface, so an
     * application can catch "the API refused the call" in one place without listing them.
     */
    public function testEveryApiExceptionSharesTheMarkerInterface() : void
    {
        $this->expectException(TwoMailException::class);

        $this->throwFor(['error' => 'Nope.', 'code' => 'quota_exceeded'], 403);
    }

    public function testTheHttpStatusIsTheExceptionCode() : void
    {
        try {
            $this->throwFor(['error' => 'Nope.', 'code' => 'quota_exceeded'], 403);
        } catch (QuotaExceededException $exception) {
            self::assertSame(403, $exception->getCode());

            return;
        }

        self::fail('Expected a QuotaExceededException.');
    }

    /**
     * An SDK older than the API must say so plainly, rather than swallow the failure or
     * report the wrong cause.
     */
    public function testAnUnknownCodeSaysTheLibraryIsOutOfDate() : void
    {
        $this->expectException(InvalidResponseCodeException::class);
        $this->expectExceptionMessage('not supported');

        $this->throwFor(['error' => 'Something new.', 'code' => 'invented_last_week'], 400);
    }

    public function testTheHumanMessageSurvives() : void
    {
        try {
            $this->throwFor(
                ['error' => 'Sending is disabled for this mailbox.', 'code' => 'quota_exceeded'],
                403
            );
        } catch (QuotaExceededException $exception) {
            self::assertStringContainsString('Sending is disabled for this mailbox.', $exception->getMessage());

            return;
        }

        self::fail('Expected a QuotaExceededException.');
    }

    /**
     * @throws TwoMailException
     */
    private function throwFor(array $response, int $httpStatus) : void
    {
        $method = new ReflectionMethod(TwoMail::class, 'throwExceptionForError');
        $method->setAccessible(true);
        $method->invoke(new TwoMail('2m_live_notarealkey'), $response, $httpStatus);
    }
}
