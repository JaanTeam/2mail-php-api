<?php

declare(strict_types=1);

namespace JaanBV\TwoMail\Exception;

/**
 * Marker interface for every exception thrown by this library.
 *
 * Catch a specific class to handle one failure, or this interface to handle "the API
 * refused the call" without listing them all:
 *
 *     try {
 *         $api->send($message);
 *     } catch (QuotaExceededException $exception) {
 *         // out of monthly credit — queue it for next month
 *     } catch (TwoMailException $exception) {
 *         // anything else the API or the transport rejected
 *     }
 */
interface TwoMailException
{
}
