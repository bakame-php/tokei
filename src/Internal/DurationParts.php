<?php

declare(strict_types=1);

namespace Bakame\Tokei\Internal;

use Bakame\Tokei\DisplaySign;
use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationFormat;
use Bakame\Tokei\InvalidDuration;
use Bakame\Tokei\Time;
use Bakame\Tokei\TimeFormat;
use Bakame\Tokei\TokeiException;
use Bakame\Tokei\Unit;
use ValueError;

use function implode;
use function intdiv;
use function number_format;
use function rtrim;
use function str_pad;
use function substr;

use const STR_PAD_LEFT;

/**
 * @internal
 */
final readonly class DurationParts
{
    private const string COMPACT_DURATION = 'compact_duration';
    private const string COMPACT_TIME = 'compact_time';

    public int $seconds;
    public int $hour;
    public int $minute;
    public int $second;
    public int $nanoseconds;
    public int $sign;

    public function __construct(Duration|Time $duration)
    {
        $duration = InputNormalizer::duration($duration);
        $remaining = $duration->seconds % 3600;
        $this->hour = intdiv($duration->seconds, 3600);
        $this->minute = intdiv($remaining, 60);
        $this->second = $remaining % 60;
        $this->nanoseconds = $duration->nanoseconds;
        $this->sign = match (true) {
            $duration->isZero() => 0,
            $duration->isNegative() => -1,
            default => 1,
        };
        $this->seconds = $duration->seconds;
    }

    /**
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    public function toDurationString(DurationFormat $format): string
    {
        return $this->toFormattedString($format, self::COMPACT_DURATION);
    }

    /**
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    public function toTimeString(TimeFormat $format): string
    {
        return $this->toFormattedString(match ($format) {
            TimeFormat::Clock => DurationFormat::Timer,
            TimeFormat::Compact => DurationFormat::Compact,
        }, self::COMPACT_TIME);
    }

    /**
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    private function toFormattedString(DurationFormat $format, string $compactType): string
    {
        return match ($format) {
            DurationFormat::Iso8601 => $this->toIso8601DurationString(),
            DurationFormat::Timer => $this->toTimerString(),
            DurationFormat::Compact => $this->toCompactString($compactType),
        };
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
    private function toTimerString(): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($this->hour, 2).':'.$pad($this->minute, 2).':'.$pad($this->second, 2);
        if (0 !== $this->nanoseconds) {
            $formatted .= '.'.match (true) {
                0 === $this->nanoseconds % 1_000_000 => substr($pad($this->nanoseconds, 9), 0, 3),
                0 === $this->nanoseconds % 1_000 => substr($pad($this->nanoseconds, 9), 0, 6),
                default => $this->nanoseconds,
            };
        }

        return -1 === $this->sign ? '-'.$formatted : $formatted;
    }

    /**
     * Returns the ISO8601 string representation of the duration.
     *
     * - fractional values are only allowed on seconds
     * - only Hour (H), Minute (M) and Seconds (S) are allowed
     * - Fraction are allowed attached only to the seconds
     * - negative marker is allowed in front of the expression
     *
     * @return non-empty-string
     */
    private function toIso8601DurationString(): string
    {
        $time = '';
        if (0 < $this->hour || 0 < $this->minute || 0 < $this->second || 0 < $this->nanoseconds) {
            $time = 'T';
            if (0 < $this->hour) {
                $time .= $this->hour.'H';
            }

            if (0 < $this->minute) {
                $time .= $this->minute.'M';
            }

            if (0 < $this->second || 0 < $this->nanoseconds) {
                $time .= $this->second;
                if (0 !== $this->nanoseconds) {
                    $time .= '.'.rtrim(str_pad((string) $this->nanoseconds, 9, '0', STR_PAD_LEFT), '0');
                }

                $time .= 'S';
            }
        }

        return '' === $time
            ? 'PT0S'
            : (-1 === $this->sign ? '-' : '').'P'.$time;
    }

    /**
     * Returns the compact string representation of a duration or a time.
     *
     * The type value adds constraint depending on the return value
     * if it is a Time we must always show the hour,minute and second part
     * For duration only values different to zero MUST be included.
     *
     * Format [-]xw xd xh xm xs xµs where x is a number.
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    private function toCompactString(string $type): string
    {
        $isClock = self::COMPACT_TIME === $type;
        $parts = $this->decompose();

        $time = [];
        if ($parts['weeks'] > 0 && !$isClock) {
            $time[] = $parts['weeks'].'w';
        }

        if ($parts['days'] > 0 && !$isClock) {
            $time[] = $parts['days'].'d';
        }

        if ($parts['hours'] > 0 || $isClock) {
            $time[] = $parts['hours'].'h';
        }

        if ($parts['minutes'] > 0 || $isClock) {
            $time[] = $parts['minutes'].'m';
        }

        if ($parts['seconds'] > 0 || ($isClock && $this->nanoseconds > 0)) {
            $time[] = $parts['seconds'].'s';
        }

        if ($parts['milliseconds'] > 0) {
            $time[] = $parts['milliseconds'].'ms';
        }

        if ($parts['microseconds'] > 0) {
            $time[] = $parts['microseconds'].'µs';
        }

        if ($parts['nanoseconds'] > 0) {
            $time[] = $parts['nanoseconds'].'ns';
        }

        return [] === $time ? '0s' : (-1 === $this->sign ? '-' : '').implode('', $time);
    }

    /**
     * Decomposes the duration into its constituent units.
     *
     * The returned values represent the duration as a combination of weeks,
     * days, hours, minutes, seconds, and the smallest applicable sub-second
     * unit. A fractional second is represented as milliseconds when it is
     * exactly divisible by a millisecond, as microseconds when it is exactly
     * divisible by a microsecond, and as nanoseconds otherwise.
     * Only one of milliseconds, microseconds, or nanoseconds is non-zero.
     *
     * @throws InvalidDuration
     *
     * @return array{
     *     weeks: int,
     *     days: int,
     *     hours: int,
     *     minutes: int,
     *     seconds: int,
     *     milliseconds: int,
     *     microseconds: int,
     *     nanoseconds: int,
     * }
     */
    public function decompose(): array
    {
        [$weeks, $remainder] = UnitTransformer::divmod(UnitTransformer::toTicks($this->seconds, Unit::Second), Unit::Week);
        [$days, $remainder] = UnitTransformer::divmod($remainder, Unit::Day);
        [$hours] = UnitTransformer::divmod($remainder, Unit::Hour);

        $isMilli = 0 === $this->nanoseconds % 1_000_000;
        $isMicro = !$isMilli && (0 === $this->nanoseconds % 1_000);

        return [
            'weeks' => $weeks,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $this->minute,
            'seconds' => $this->second,
            'milliseconds' => $isMilli ? intdiv($this->nanoseconds, 1_000_000) : 0,
            'microseconds' => $isMicro ? intdiv($this->nanoseconds, 1_000) : 0,
            'nanoseconds' => !$isMilli && !$isMicro ? $this->nanoseconds : 0,
        ];
    }

    /**
     * Returns the duration expressed in the given unit.
     */
    public function toNumber(Unit $unit): int|float
    {
        $ticksPerUnit = UnitTransformer::ticks($unit);
        $ticksPerSecond = UnitTransformer::ticks(Unit::Second);

        return $this->sign * (
            ($ticksPerUnit >= $ticksPerSecond)
                ? ($this->seconds / ($ticksPerUnit / $ticksPerSecond) + $this->nanoseconds / $ticksPerUnit)
                : ($this->seconds * ($ticksPerSecond / $ticksPerUnit) + $this->nanoseconds / $ticksPerUnit)
        );
    }

    /**
     * Returns the duration expressed in the given unit as a numeric string.
     *
     * @return non-empty-string
     */
    public function toNumberString(Unit $unit, int $precision, DisplaySign $displaySign = DisplaySign::Auto): string
    {
        0 <= $precision || throw new ValueError('The precision cannot be negative.');

        $value = $this->toNumber($unit);
        $number = number_format(num: $value, decimals: $precision, thousands_separator: '');

        return (0 <= $value && DisplaySign::Always === $displaySign)
            ? '+'.$number
            : $number;
    }
}
