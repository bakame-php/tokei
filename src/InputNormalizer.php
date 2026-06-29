<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * @internal
 */
final readonly class InputNormalizer
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidTime
     */
    public static function time(Time|Event|NativeEvent|DateTimeInterface $time): Time
    {
        return match (true) {
            $time instanceof Time => $time,
            $time instanceof DateTimeInterface => Time::fromDateTime($time),
            $time instanceof Event => $time->at,
            default => Event::fromNative($time)->at,
        };
    }

    /**
     * @throws InvalidDuration
     */
    public static function duration(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): Duration
    {
        return match (true) {
            $duration instanceof Duration => $duration,
            $duration instanceof DateInterval => Duration::fromDateInterval($duration),
            default => self::interval($duration)->duration,
        };
    }

    public static function interval(Interval|Task|NativeTask|NativeInterval $interval): Interval
    {
        return match (true) {
            $interval instanceof Interval => $interval,
            $interval instanceof Task => $interval->interval,
            $interval instanceof NativeInterval => Interval::fromNative($interval),
            default => Task::fromNative($interval)->interval,
        };
    }

    /**
     * @param DateTimeInterface|DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException if the timezone identifier is invalid
     */
    public static function timezone(DateTimeInterface|DateTimeZone|string $timezone): DateTimeZone
    {
        if ($timezone instanceof DateTimeInterface) {
            return $timezone->getTimezone();
        }

        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Throwable $exception) {
            throw TimeException::dueToInvalidTimezone(timezone: $timezone, previous: $exception);
        }
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public static function identifiers(Identifiers|HasIdentifiers|string $identifier = new Identifiers()): Identifiers
    {
        return match (true) {
            $identifier instanceof Identifiers => $identifier,
            $identifier instanceof HasIdentifiers => $identifier->identifiers,
            default => new Identifiers($identifier),
        };
    }
}
