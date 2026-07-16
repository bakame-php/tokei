<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

use function implode;
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

    public function __construct(
        public int $hour,
        public int $minute,
        public int $second,
        public int $microsecond,
        public int $sign,
    ) {
    }

    public static function parse(int $value): self
    {
        $sign = $value <=> 0 ;
        $microsecond = -1 === $sign ? -$value : $value;
        [$hour, $microsecond] = UnitTransformer::divmod($microsecond, Unit::Hour);
        [$minute, $microsecond] = UnitTransformer::divmod($microsecond, Unit::Minute);
        [$second, $microsecond] = UnitTransformer::divmod($microsecond, Unit::Second);

        return new self(
            hour: $hour,
            minute: $minute,
            second: $second,
            microsecond: $microsecond,
            sign: $sign,
        );
    }

    public function build(): int
    {
        return $this->sign * (
            UnitTransformer::toTicks($this->hour, Unit::Hour)
            + UnitTransformer::toTicks($this->minute, Unit::Minute)
            + UnitTransformer::toTicks($this->second, Unit::Second)
            + $this->microsecond
        );
    }

    /**
     * Converts the instance to an DateInterval object.
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        [$interval->d, $remainder] = UnitTransformer::divmod($this->build(), Unit::Day);
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
     * @return non-empty-string
     */
    public function toDurationString(DurationFormat $format): string
    {
        return $this->toFormattedString($format, self::COMPACT_DURATION);
    }

    /**
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
     * @return non-empty-string
     */
    private function toCompactString(string $type): string
    {
        $isClock = self::COMPACT_TIME === $type;
        [$weeks, $remainder] = UnitTransformer::divmod($this->build(), Unit::Week);
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
