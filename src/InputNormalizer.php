<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeInterface;

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
}
