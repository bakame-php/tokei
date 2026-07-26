<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

use function implode;
use function intdiv;
use function rtrim;
use function str_pad;

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
    public int $microsecond;
    public int $sign;

    public function __construct(Duration $duration)
    {
        $remaining = $duration->seconds % 3600;
        $this->hour = intdiv($duration->seconds, 3600);
        $this->minute = intdiv($remaining, 60);
        $this->second = $remaining % 60;
        $this->microsecond = $duration->microseconds;
        $this->sign = $duration->sign;
        $this->seconds = $duration->seconds;
    }

    /**
     * @throws InvalidDuration
     */
    private function toInt(): int
    {
        return $this->sign * (UnitTransformer::toTicks($this->seconds, Unit::Second) + $this->microsecond);
    }

    /**
     * Converts the instance to an DateInterval object.
     *
     * @throws TokeiException
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        [$interval->d, $remainder] = UnitTransformer::divmod($this->toInt(), Unit::Day);
        [$interval->h] = UnitTransformer::divmod($remainder, Unit::Hour);
        $interval->i = $this->minute;
        $interval->s = $this->second;
        if (0 !== $this->microsecond) {
            $interval->f = UnitTransformer::fromTicks($this->microsecond, Unit::Second);
        }
        $interval->invert = -1 === $this->sign ? 1 : 0;
        if (null === $relativeTo) {
            return $interval;
        }

        if (!$relativeTo instanceof DateTimeImmutable) {
            $relativeTo = DateTimeImmutable::createFromInterface($relativeTo);
        }

        return $relativeTo->diff($relativeTo->add($interval));
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
        if (0 !== $this->microsecond) {
            $formatted .= '.'.$pad($this->microsecond, 6);
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
        if (0 < $this->hour || 0 < $this->minute || 0 < $this->second || 0 < $this->microsecond) {
            $time = 'T';
            if (0 < $this->hour) {
                $time .= $this->hour.'H';
            }

            if (0 < $this->minute) {
                $time .= $this->minute.'M';
            }

            if (0 < $this->second || 0 < $this->microsecond) {
                $time .= $this->second;
                if (0 !== $this->microsecond) {
                    $time .= '.'.rtrim(str_pad((string) $this->microsecond, 6, '0', STR_PAD_LEFT), '0');
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
        $ticks = $this->toInt();
        if (-1 === $this->sign) {
            $ticks = -$ticks;
        }
        [$weeks, $remainder] = UnitTransformer::divmod($ticks, Unit::Week);
        [$days, $remainder] = UnitTransformer::divmod($remainder, Unit::Day);
        [$hours] = UnitTransformer::divmod($remainder, Unit::Hour);

        $time = [];
        if ($weeks > 0 && !$isClock) {
            $time[] = $weeks.'w';
        }

        if ($days > 0 && !$isClock) {
            $time[] = $days.'d';
        }

        if ($hours > 0 || $isClock) {
            $time[] = $hours.'h';
        }

        if ($this->minute > 0 || $isClock) {
            $time[] = $this->minute.'m';
        }

        if ($this->second > 0 || ($isClock && $this->microsecond > 0)) {
            $time[] = $this->second.'s';
        }

        if ($this->microsecond > 0) {
            [$milli, $remainder] = UnitTransformer::divmod($this->microsecond, Unit::Millisecond);
            $time[] = 0 === $remainder ? $milli.'ms' : $this->microsecond.'µs';
        }

        return [] === $time ? '0s' : (-1 === $this->sign ? '-' : '').implode('', $time);
    }
}
