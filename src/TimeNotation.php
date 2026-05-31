<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function implode;
use function preg_match;
use function str_pad;
use function substr;
use function trim;

use const STR_PAD_LEFT;

enum TimeNotation
{
    case Compact;
    case Iso8601;

    private const string REGEXP_ISO8601 = '/^
        (?<hour>\d{1,2}):
        (?<minute>\d{1,2})
        (:(?<second>\d{1,2}))?
        (?:\.(?<microsecond>\d{1,6}))?
    $/x';

    private const string REGEXP_COMPACT = '@^
        (?<hour>\d+)\s*h\s*
        (?<minute>\d+)\s*m\s*
        (?:(?<second>\d+)\s*s\s*)?
        (?:(?<microsecond>\d+)\s*(µs|us)\s*)?
    $@x';

    /**
     * @throws InvalidTime
     */
    public function decode(string $notation): Time
    {
        $regexp = match ($this) {
            self::Iso8601 => self::REGEXP_ISO8601,
            self::Compact => self::REGEXP_COMPACT,
        };

        $notation = trim($notation);
        1 === preg_match($regexp, $notation, $parts) || throw new InvalidTime('Unknown or bad format `'.$notation.'`.');

        return Time::at(
            hour: (int) $parts['hour'],
            minute: (int) $parts['minute'],
            second: (int) ($parts['second'] ?? 0),
            microsecond: (int) str_pad(substr($parts['microsecond'] ?? '0', 0, 6), 6, '0'),
        );
    }

    /**
     * @return non-empty-string
     */
    public function encode(Time $time): string
    {
        return match ($this) {
            self::Iso8601 => self::toIso8601($time),
            self::Compact => self::toCompact($time),
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
    private static function toIso8601(Time $time): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($time->hour, 2).':'.$pad($time->minute, 2).':'.$pad($time->second, 2);
        if (0 !== $time->microsecond) {
            $formatted .= '.'.$pad($time->microsecond, 6);
        }

        return $formatted;
    }

    /**
     * Format xhxmxsxµs where x is a number.
     *
     * @return non-empty-string
     */
    private static function toCompact(Time $time): string
    {
        $parts = [];
        $parts[] = $time->hour.'h';
        $parts[] = $time->minute.'m';
        if (0 !== $time->second || 0 !== $time->microsecond) {
            $parts[] = $time->second.'s';
        }

        if (0 !== $time->microsecond) {
            $parts[] = $time->microsecond.'µs';
        }

        return implode('', $parts);
    }
}
