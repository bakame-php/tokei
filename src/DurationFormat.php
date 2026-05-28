<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function implode;
use function rtrim;
use function str_pad;

use const STR_PAD_LEFT;

enum DurationFormat
{
    case Iso8601;
    case Compact;
    case Clock;

    /**
     * @return non-empty-string
     */
    public function format(Duration $duration, SubSecondDisplay $subSecondDisplay = SubSecondDisplay::Auto): string
    {
        $includeSubSeconds = match ($subSecondDisplay) {
            SubSecondDisplay::Always => true,
            SubSecondDisplay::Never => false,
            SubSecondDisplay::Auto => 0 !== $duration->microseconds,
        };

        return match ($this) {
            DurationFormat::Clock => self::toClockFormat($duration, $includeSubSeconds),
            DurationFormat::Iso8601 => self::toIso8601($duration, $includeSubSeconds),
            DurationFormat::Compact => self::toCompact($duration, $includeSubSeconds),
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
    private static function toClockFormat(Duration $duration, bool $includeSubSeconds): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $duration->hours.':'.$pad($duration->minutes, 2).':'.$pad($duration->seconds, 2);
        if ($includeSubSeconds) {
            $formatted .= '.'.$pad($duration->microseconds, 6);
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
    private static function toIso8601(Duration $duration, bool $includeSubSeconds): string
    {
        $time = '';
        $hours = $duration->hours % 24;
        if (0 !== $hours) {
            $time .= $hours.'H';
        }

        if (0 !== $duration->minutes) {
            $time .= $duration->minutes.'M';
        }

        $seconds = (string) $duration->seconds;
        if ($includeSubSeconds) {
            $seconds .= '.'.rtrim(str_pad((string) $duration->microseconds, 6, '0', STR_PAD_LEFT), '0');
        }

        if ('0' !== $seconds) {
            $time .= $seconds.'S';
        }

        return  (0 === $duration->daysCount && '' === $time)
            ? 'PT0S'
            : (-1 === $duration->sign ? '-' : '').'P'.(0 !== $duration->daysCount ? $duration->daysCount.'D' : '').('' !== $time ? 'T'.$time : '');
    }

    /**
     * @return non-empty-string
     */
    private static function toCompact(Duration $duration, bool $includeSubSeconds): string
    {
        $time = [];
        if (0 !== $duration->weeksCount) {
            $time[] = $duration->weeksCount.'w';
        }

        $days = $duration->daysCount % 7;
        if (0 !== $days) {
            $time[] = $days.'d';
        }

        $hours = $duration->hours % 24;
        if (0 !== $hours) {
            $time[] = $hours.'h';
        }

        if (0 !== $duration->minutes) {
            $time[] = $duration->minutes.'m';
        }

        if (0 !== $duration->seconds) {
            $time[] = $duration->seconds.'s';
        }

        if ($includeSubSeconds) {
            $time[] = $duration->microseconds.'µs';
        }

        return  [] === $time
            ? ($includeSubSeconds ? '0µs' : '0s')
            : (-1 === $duration->sign ? '-' : '').implode(' ', $time);
    }
}
