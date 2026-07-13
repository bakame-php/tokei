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
    private const int HOURS_PER_DAY = 24;
    private const int HOURS_PER_WEEK = self::HOURS_PER_DAY * 7;
    private const string COMPACT_DURATION = 'compact_duration';
    private const string COMPACT_TIME = 'compact_time';

    public function __construct(
        public int $hours,
        public int $minutes,
        public int $seconds,
        public int $microseconds,
        public int $sign,
    ) {
    }

    public static function parse(int $value): self
    {
        $sign = $value <=> 0 ;
        $microseconds = -1 === $sign ? -$value : $value;
        [$hours, $microseconds] = UnitTransformer::divmod($microseconds, Unit::Hour);
        [$minutes, $microseconds] = UnitTransformer::divmod($microseconds, Unit::Minute);
        [$seconds, $microseconds] = UnitTransformer::divmod($microseconds, Unit::Second);

        return new self(
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: $microseconds,
            sign: $sign,
        );
    }

    public function build(): int
    {
        return $this->sign * (
            UnitTransformer::toMicroseconds($this->hours, Unit::Hour)
            + UnitTransformer::toMicroseconds($this->minutes, Unit::Minute)
            + UnitTransformer::toMicroseconds($this->seconds, Unit::Second)
            + $this->microseconds
        );
    }

    /**
     * @return non-empty-string
     */
    public function formatDuration(DurationFormat $format): string
    {
        return $this->format($format, self::COMPACT_DURATION);

    }

    /**
     * @return non-empty-string
     */
    public function formatTime(TimeFormat $format): string
    {
        return $this->format(match ($format) {
            TimeFormat::Clock => DurationFormat::Timer,
            TimeFormat::Compact => DurationFormat::Compact,
        }, self::COMPACT_TIME);
    }

    /**
     * @return non-empty-string
     */
    private function format(DurationFormat $format, string $compactType): string
    {
        return match ($format) {
            DurationFormat::Iso8601 => $this->asIso8601Duration(),
            DurationFormat::Timer => $this->asTimer(),
            DurationFormat::Compact => $this->asCompact($compactType),
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
    private function asTimer(): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($this->hours, 2).':'.$pad($this->minutes, 2).':'.$pad($this->seconds, 2);
        if (0 !== $this->microseconds) {
            $formatted .= '.'.$pad($this->microseconds, 6);
        }

        return -1 === $this->sign ? '-'.$formatted : $formatted;
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
    private function asIso8601Duration(): string
    {
        $time = '';
        if (0 < $this->hours || 0 < $this->minutes || 0 < $this->seconds || 0 < $this->microseconds) {
            $time = 'T';
            if (0 < $this->hours) {
                $time .= $this->hours.'H';
            }

            if (0 < $this->minutes) {
                $time .= $this->minutes.'M';
            }

            if (0 < $this->seconds || 0 < $this->microseconds) {
                $time .= $this->seconds;
                if (0 !== $this->microseconds) {
                    $time .= '.'.rtrim(str_pad((string) $this->microseconds, 6, '0', STR_PAD_LEFT), '0');
                }

                $time .= 'S';
            }
        }

        return '' === $time
            ? 'PT0S'
            : (-1 === $this->sign ? '-' : '').'P'.$time;
    }

    /**
     * Format [-]xw xd xh xm xs xµs where x is a number.
     * @return non-empty-string
     */
    private function asCompact(string $type): string
    {
        $isClock = self::COMPACT_TIME === $type;
        $totalHours = $this->hours;
        $weeks = intdiv($totalHours, self::HOURS_PER_WEEK);
        $remainingHours = $totalHours % self::HOURS_PER_WEEK;
        $days = intdiv($remainingHours, self::HOURS_PER_DAY);
        $hours = $remainingHours % self::HOURS_PER_DAY;

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

        if (0 !== $this->minutes || $isClock) {
            $time[] = $this->minutes.'m';
        }

        if (0 !== $this->seconds || ($isClock && 0 !== $this->microseconds)) {
            $time[] = $this->seconds.'s';
        }

        if (0 !== $this->microseconds) {
            $time[] = 0 === $this->microseconds % 1000
                ? intdiv($this->microseconds, 1000).'ms'
                : $this->microseconds.'µs';
        }

        return [] === $time ? '0s' : (-1 === $this->sign ? '-' : '').implode('', $time);
    }

    /**
     * Converts the instance to an DateInterval object.
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        [$interval->d] = UnitTransformer::divmod($this->build(), Unit::Day);
        $interval->h = $this->hours % 24;
        $interval->i = $this->minutes;
        $interval->s = $this->seconds;
        if (0 !== $this->microseconds) {
            $interval->f = UnitTransformer::fromMicroseconds($this->microseconds, Unit::Second);
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
}
