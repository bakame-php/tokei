<?php

declare(strict_types=1);

namespace Bakame\Tokei\Internal;

use Bakame\Tokei\DurationFormat;
use Bakame\Tokei\InvalidDuration;
use Bakame\Tokei\InvalidTime;
use Bakame\Tokei\TimeFormat;
use Bakame\Tokei\TokeiException;
use Bakame\Tokei\Unit;
use DateInterval;
use Time\Duration as TimeDuration;

use function preg_match;
use function str_pad;
use function trim;

/**
 * Parse Time and/or Duration.
 *
 * @internal
 *
 * @phpstan-type InputDurationPart ''|numeric-string|int
 * @phpstan-type InputSign ''|'+'|'-'
 */
final readonly class Parser
{
    private const string REGEXP_DURATION_TIMER = '@^
        (?<sign>-)?\s*
        (?<hours>\d+)\s*:\s*
        (?<minutes>\d{1,2})\s*:\s*
        (?<seconds>\d{1,2})
        ((\.|")(?<fractions>\d{1,9}))?\s*
    $@x';

    private const string REGEXP_DURATION_COMPACT = '@^
        (?<sign>-)?\s*
        (?:(?<weeks>\d+)\s*w\s*)?
        (?:(?<days>\d+)\s*d\s*)?
        (?:(?<hours>\d+)\s*h\s*)?
        (?:(?<minutes>\d+)\s*m\s*)?
        (?:(?<seconds>\d+)\s*s\s*)?
        (?:
            (?<fractions>\d{1,9})\s*
            (?<unit>µs|us|ms|ns)\s*
        )?
    $@x';

    private const string REGEXP_DURATION_ISO8601 = '@^
        (?<sign>[+-])?
        P
        (?=.*?(?:\d+W|\d+D|T\d+H|T\d+M|T\d+(?:[\.,]\d+)?S))
        (?:(?<weeks>\d+)W)?
        (?:(?<days>\d+)D)?
        (?:T
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:
                (?<seconds>\d+)
                (?:[\.,](?<fractions>\d{1,9}))?
                S
            )?
        )?
    $@x';

    private const string REGEXP_TIMER_ISO8601 = '@^
        (?<hour>\d{1,2})\s*:\s*
        (?<minute>\d{1,2})
        (\s*:\s*
            (?<second>\d{1,2}))?
            (?:\.(?<fractions>\d{1,9})
        \s*)?
    $@x';

    private const string REGEXP_TIMER_COMPACT = '@^
        (?<hour>\d{1,2})\s*h\s*
        (?<minute>\d{1,2})\s*m\s*
        (?:(?<second>\d{1,2})\s*s\s*)?
        (?:
            (?<fractions>\d{1,9})\s*
            (?<unit>µs|us|ms|ns)\s*
        )?
    $@x';

    private function __construct()
    {
    }

    /**
     * @throws TokeiException
     */
    public static function parseTime(string $specification, TimeFormat $format): TimeComponents
    {
        $notation = trim($specification);
        '' !== $notation || throw new InvalidTime('the submitted notation is empty.');

        $regexp = match ($format) {
            TimeFormat::Clock => self::REGEXP_TIMER_ISO8601,
            TimeFormat::Compact => self::REGEXP_TIMER_COMPACT,
        };

        1 === preg_match($regexp, $notation, $parts) || throw new InvalidTime('Unknown or bad time format `'.$specification.'`'.'`.');

        if (self::REGEXP_TIMER_ISO8601 === $regexp) {
            $parts['fractions'] = (int) str_pad($parts['fractions'] ?? '0', 9, '0');
        }

        return new TimeComponents(
            hour: (int) $parts['hour'],
            minute: (int) $parts['minute'],
            second: (int) ($parts['second'] ?? 0),
            nanosecond: self::resolveNanoseconds($parts),
        );
    }

    /**
     * @throws TokeiException
     */
    public static function parseNativeDuration(TimeDuration $duration): DurationComponents
    {
        return self::toDurationComponents([
            'seconds' => $duration->seconds,
            'nanoseconds' => $duration->nanoseconds,
            'sign' => $duration->negative ? '-' : '+',
        ]);
    }

    /**
     * @throws TokeiException
     */
    public static function parseDateInterval(DateInterval $interval): DurationComponents
    {
        false !== $interval->days || (0 === $interval->y && 0 === $interval->m) || throw new InvalidDuration('fromDateInterval() does not handle non deterministic DateInterval properties like months and years.');
        (0.0 <= $interval->f && 1.0 > $interval->f) || throw new InvalidDuration('Invalid fractional seconds in DateInterval.');

        $days = false === $interval->days ? $interval->d : $interval->days;

        return self::toDurationComponents([
            'days' => $days,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'seconds' => $interval->s,
            'nanoseconds' => UnitTransformer::toTicks($interval->f, Unit::Second),
            'sign' => 1 === $interval->invert ? '-' : '+',
        ]);
    }

    /**
     * @throws TokeiException
     */
    public static function parseDurationNotation(string $specification, DurationFormat $format): DurationComponents
    {
        $notation = trim($specification);
        '' !== $notation || throw new InvalidDuration('the submitted notation is empty.');

        return match ($format) {
            DurationFormat::Iso8601 => self::parseDurationIso8601($notation),
            DurationFormat::Timer => self::parseDurationTimer($notation),
            DurationFormat::Compact => self::parseDurationCompact($notation),
        };
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * Fractional values are allowed but with only one unit per notation.
     * 3m3ms is allowed
     * 3m3ms3µs is disallowed
     *
     * The fractions supported can be expressed in:
     *
     * - milliseconds
     * - microseconds
     *
     * @param non-empty-string $notation
     *
     * @throws TokeiException
     */
    private static function parseDurationCompact(string $notation): DurationComponents
    {
        1 === preg_match(self::REGEXP_DURATION_COMPACT, $notation, $parts) || throw new InvalidDuration('Unknown or bad format `'.$notation.'`.');

        $parts['nanoseconds'] = self::resolveNanoseconds($parts);

        return self::toDurationComponents($parts);
    }

    /**
     * Parses and returns a new instance from ISO8601 string representation.
     *
     * Because the duration does not handle in a deterministic way month and year components
     * the following restrictions apply:
     *
     * - only W, D, H, S are allowed
     * - Y is rejected
     * - M is only allowed in the time section (PT30M) to represents minutes
     * - fractional values are only allowed on seconds
     * - at least one unit must exist
     * - negative marker is allowed in front of the expression
     *
     * @param non-empty-string $notation
     *
     * @throws TokeiException
     */
    private static function parseDurationIso8601(string $notation): DurationComponents
    {
        1 === preg_match(self::REGEXP_DURATION_ISO8601, $notation, $parts) || throw InvalidDuration::dueToMalformedIso8601($notation);

        $parts['fractions'] = (int) (str_pad($parts['fractions'] ?? '0', 9, '0'));
        $parts['nanoseconds'] = self::resolveNanoseconds($parts);

        return self::toDurationComponents($parts);
    }

    /**
     * @param array{fractions?: InputDurationPart, unit?: ?non-empty-string} $parts
     *
     * @throws InvalidDuration
     */
    private static function resolveNanoseconds(array $parts): int
    {
        $value = (int) ($parts['fractions'] ?? 0);
        $value >= 0 || throw new InvalidDuration('the fraction cannot be negative.');
        $unit = $parts['unit'] ?? 'ns';
        if ('ms' === $unit) {
            $value < 1_000 || throw new InvalidDuration('millisecond fraction value cannot be greater than 999.');

            return $value * 1_000_000;
        }

        if ('µs' === $unit || 'us' === $unit) {
            $value < 1_000_000 || throw new InvalidDuration('microsecond fraction value cannot be greater than 999_999.');

            return $value * 1_000;
        }

        $value < 1_000_000_000 || throw new InvalidDuration('nanosecond fraction value cannot be greater than 999_999_999.');

        return $value;
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @param non-empty-string $notation
     *
     * @throws TokeiException
     */
    private static function parseDurationTimer(string $notation): DurationComponents
    {
        1 === preg_match(self::REGEXP_DURATION_TIMER, $notation, $parts) || throw new InvalidDuration('Unknown or bad format `'.$notation.'`.');

        $hours = (int) $parts['hours'];
        $minutes = (int) $parts['minutes'];
        $seconds = (int) $parts['seconds'];
        $nanoseconds = (int) (str_pad($parts['fractions'] ?? '0', 9, '0'));

        ($minutes >= 0 && $minutes < 60) || throw InvalidDuration::dueToMalformedTime($minutes, Unit::Minute);
        ($seconds >= 0 && $seconds < 60) || throw InvalidDuration::dueToMalformedTime($seconds, Unit::Second);
        ($nanoseconds >= 0 && $nanoseconds < 1_000_000_000) || throw InvalidDuration::dueToMalformedTime($nanoseconds, Unit::Microsecond);

        return self::toDurationComponents([
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'nanoseconds' => $nanoseconds,
            'sign' => '-' === $parts['sign'] ? '-' : '+',
        ]);
    }

    /**
     * @param array{
     *     weeks? : InputDurationPart,
     *     days? : InputDurationPart,
     *     hours? : InputDurationPart,
     *     minutes? : InputDurationPart,
     *     seconds? : InputDurationPart,
     *     nanoseconds? : InputDurationPart,
     *     sign? : InputSign,
     * } $parts
     *
     * @throws TokeiException
     */
    private static function toDurationComponents(array $parts): DurationComponents
    {
        $seconds = 0;
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['weeks'] ?? 0), Unit::Week, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['days'] ?? 0), Unit::Day, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['hours'] ?? 0), Unit::Hour, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['minutes'] ?? 0), Unit::Minute, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) ($parts['seconds'] ?? 0));

        $nanoseconds = UnitTransformer::add(0, (int) ($parts['nanoseconds'] ?? 0));

        $sign = '-' === ($parts['sign'] ?? '+') ? -1 : 1;

        return new DurationComponents($seconds * $sign, $nanoseconds * $sign);
    }
}
