<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function implode;
use function preg_match;
use function rtrim;
use function str_pad;
use function trim;

use const STR_PAD_LEFT;

enum DurationFormat
{
    case Iso8601;
    case Compact;
    case Timer;

    private const string REGEXP_TIMER = '@^
        (?<sign>-)?\s*
        (?<hours>\d+):
        (?<minutes>\d{1,2}):
        (?<seconds>\d{1,2})
        (\.(?<microseconds>\d+))?
    $@x';

    private const string REGEXP_COMPACT = '@^
        (?<sign>-)?\s*
        (?:(?<weeks>\d+)\s*w\s*)?
        (?:(?<days>\d+)\s*d\s*)?
        (?:(?<hours>\d+)\s*h\s*)?
        (?:(?<minutes>\d+)\s*m\s*)?
        (?:(?<seconds>\d+)\s*s\s*)?
        (?:(?<microseconds>\d+)\s*(µs|us)\s*)?
    $@x';

    private const string REGEXP_ISO8601 = '@^
        (?<sign>[+-])?
        P
        (?=.*?(?:\d+W|\d+D|T\d+H|T\d+M|T\d+(?:\.\d+)?S)) # look-ahead to restrict support for ISO8601 formats
        (?:(?<weeks>\d+)W)?
        (?:(?<days>\d+)D)?
        (?:T
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:(?<seconds>\d+(?:\.\d+)?)S)?
        )?
    $@x';

    /**
     * Returns a new Duration instance from a string notation representation.
     *
     * @throws InvalidDuration
     */
    public function decode(string $notation): Duration
    {
        return match ($this) {
            self::Iso8601 => self::fromIso8601($notation),
            self::Timer => self::fromTimer($notation),
            self::Compact => self::fromCompact($notation),
        };
    }

    /**
     * Encodes a Duration into a specified string notation representation.
     *
     * @return non-empty-string
     */
    public function encode(Duration $duration): string
    {
        return match ($this) {
            self::Iso8601 => self::toIso8601($duration),
            self::Timer => self::toTimer($duration),
            self::Compact => self::toCompact($duration),
        };
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws InvalidDuration
     */
    private function fromTimer(string $duration): Duration
    {
        1 === preg_match(self::REGEXP_TIMER, $duration, $parts) || throw new InvalidDuration('Unknown or bad format `'.$duration.'`.');

        $minutes = (int) $parts['minutes'];
        $seconds = (int) $parts['seconds'];
        $microseconds = (int) ($parts['microseconds'] ?? '0');

        ($minutes >= 0 && $minutes < 60) || throw InvalidDuration::dueToMalformedMinute($minutes);
        ($seconds >= 0 && $seconds < 60) || throw InvalidDuration::dueToMalformedSecond($seconds);
        ($microseconds >= 0 && $microseconds < 1_000_000) || throw InvalidDuration::dueToMalformedMicrosecond($microseconds);

        /** @var non-negative-int $microseconds */
        $microseconds = UnitTransformer::compose(
            days: 0,
            hours: (int) $parts['hours'],
            minutes: $minutes,
            seconds: $seconds,
            microseconds: $microseconds,
            sign: 1
        );

        $duration = Duration::of(microseconds: $microseconds);

        return '-' === $parts['sign'] ? $duration->negated() : $duration;
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws InvalidDuration
     */
    private function fromCompact(string $data): Duration
    {
        $data = trim($data);

        ('' !== $data && 1 === preg_match(self::REGEXP_COMPACT, $data, $parts)) || throw new InvalidDuration('Unknown or bad format `'.$data.'`.');

        /** @var non-negative-int $microseconds */
        $microseconds = UnitTransformer::compose(
            days: (((int) ($parts['weeks'] ?? 0) * 7) + (int) ($parts['days'] ?? 0)),
            hours: (int) ($parts['hours'] ?? 0),
            minutes: (int) ($parts['minutes'] ?? 0),
            seconds: (int) ($parts['seconds'] ?? 0),
            microseconds: (int) ($parts['microseconds'] ?? 0),
            sign: 1
        );

        $duration = Duration::of(microseconds: $microseconds);

        return '-' === ($parts['sign'] ?? '') ? $duration->negated() : $duration;
    }

    /**
     * Parses and returns a new instance from ISO8601 string representation.
     *  Because the duration does not handle in a deterministic way month and year components
     * the following restrictions apply:
     *
     * - only W, D, H, S are allowed
     * - Y is rejected
     * - M is only allowed in the time section (PT30M) to represents minutes
     * - fractional values are only allowed on seconds
     * - at least one unit must exist
     * - negative marker is allowed in front of the expression
     *
     * @throws InvalidDuration
     */
    private function fromIso8601(string $data): Duration
    {
        1 === preg_match(self::REGEXP_ISO8601, $data, $parts) || throw InvalidDuration::dueToMalformedIso8601($data);

        /** @var non-negative-int $microseconds */
        $microseconds = UnitTransformer::compose(
            days: (((int) ($parts['weeks'] ?? 0) * 7) + (int) ($parts['days'] ?? 0)),
            hours: (int) ($parts['hours'] ?? 0),
            minutes: (int) ($parts['minutes'] ?? 0),
            seconds: (float) ($parts['seconds'] ?? 0),
            microseconds: 0,
            sign: 1,
        );

        $duration = Duration::of(microseconds: $microseconds);

        return '-' === ($parts['sign'] ?? '') ? $duration->negated() : $duration;
    }

    /**
     * Returns the string representation of the Duration.
     *
     * The following format is used [-]HH:MM:SS[.mmmmmm]
     * the fraction and the signed are only display if
     * they duration is negative and/or the sub seconds
     * fraction is different from 0
     *
     * @return non-empty-string
     */
    private static function toTimer(Duration $duration): string
    {
        $value = $duration->value;

        $abs = $value < 0 ? -$value : $value;
        $hours = UnitTransformer::whole($abs, Unit::Hour);
        $abs = UnitTransformer::remainder($abs, Unit::Hour);
        $minutes = UnitTransformer::whole($abs, Unit::Minute);
        $abs = UnitTransformer::remainder($abs, Unit::Minute);
        $seconds = UnitTransformer::whole($abs, Unit::Second);
        $microseconds = UnitTransformer::remainder($abs, Unit::Second);

        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($hours, 2).':'.$pad($minutes, 2).':'.$pad($seconds, 2);
        if (0 !== $microseconds) {
            $formatted .= '.'.$pad($microseconds, 6);
        }

        return -1 === $duration->sign ? '-'.$formatted : $formatted;
    }

    /**
     * Returns the ISO8601 string representation of the duration.
     *
     * - fractional values are only allowed on seconds
     * - only D, H, M and S are allowed; M represents the minutes
     * - negative marker is allowed in front of the expression
     *
     * @return non-empty-string
     */
    private static function toIso8601(Duration $duration): string
    {
        $value = $duration->value;
        $sign = -1 === $duration->sign ? '-' : '';

        $abs = $value < 0 ? -$value : $value;
        $days = UnitTransformer::whole($abs, Unit::Day);
        $abs = UnitTransformer::remainder($abs, Unit::Day);
        $hours = UnitTransformer::whole($abs, Unit::Hour);
        $abs = UnitTransformer::remainder($abs, Unit::Hour);
        $minutes = UnitTransformer::whole($abs, Unit::Minute);
        $abs = UnitTransformer::remainder($abs, Unit::Minute);
        $seconds = UnitTransformer::whole($abs, Unit::Second);
        $microseconds = UnitTransformer::remainder($abs, Unit::Second);

        $time = '';
        if (0 < $hours || 0 < $minutes || 0 < $seconds || 0 < $microseconds) {
            $time = 'T';
            if (0 < $hours) {
                $time .= $hours.'H';
            }

            if (0 < $minutes) {
                $time .= $minutes.'M';
            }

            if (0 < $seconds || 0 < $microseconds) {
                $time .= $seconds;
                if (0 !== $microseconds) {
                    $time .= '.'.rtrim(str_pad((string) $microseconds, 6, '0', STR_PAD_LEFT), '0');
                }

                $time .= 'S';
            }
        }

        $date = 0 !== $days ? $days.'D' : '';

        return ('' === $date && '' === $time)
            ? 'PT0S'
            : $sign.'P'.$date.$time;
    }

    /**
     * Format [-]xw xd xh xm xs xµs where x is a number.
     * @return non-empty-string
     */
    private static function toCompact(Duration $duration): string
    {
        $parsed = UnitTransformer::decompose($duration->value);
        $time = [];
        if (0 !== $parsed->weeksCount) {
            $time[] = $parsed->weeksCount.'w';
        }

        $days = $parsed->daysCount % 7;
        if (0 !== $days) {
            $time[] = $days.'d';
        }

        $hours = $parsed->hours % 24;
        if (0 !== $hours) {
            $time[] = $hours.'h';
        }

        if (0 !== $parsed->minutes) {
            $time[] = $parsed->minutes.'m';
        }

        if (0 !== $parsed->seconds) {
            $time[] = $parsed->seconds.'s';
        }

        if (0 !== $parsed->microseconds) {
            $time[] = $parsed->microseconds.'µs';
        }

        return [] === $time ? '0s' : (-1 === $parsed->sign ? '-' : '').implode('', $time);
    }
}
