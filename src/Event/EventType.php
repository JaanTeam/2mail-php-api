<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Event;

/**
 * The event types the pipeline reports, for TwoMail::events() filters and webhook
 * subscriptions.
 *
 * Constants rather than a native enum on purpose: the API may add a type before this
 * library knows about it, and an unknown case must not make an existing application throw.
 * Event::event() therefore returns the raw string; compare it against these.
 */
final class EventType
{
    /** Handed to the relay by the API or SMTP. */
    public const ACCEPTED = 'accepted';

    /** The receiving server accepted it. */
    public const DELIVERED = 'delivered';

    /** Temporarily refused; it will be retried. */
    public const DEFERRED = 'deferred';

    /** Permanently refused by the receiving server. */
    public const BOUNCED = 'bounced';

    /** The tracking pixel in an HTML body was loaded. */
    public const OPENED = 'opened';

    /** 2mail itself refused to send — suppression list, invalid address, limit reached. */
    public const REJECTED = 'rejected';

    /** Delivery could not be completed and will not be retried. */
    public const FAILED = 'failed';

    public const ALL = [
        self::ACCEPTED,
        self::DELIVERED,
        self::DEFERRED,
        self::BOUNCED,
        self::OPENED,
        self::REJECTED,
        self::FAILED,
    ];

    /**
     * Static holder, never instantiated.
     */
    private function __construct()
    {
    }
}
