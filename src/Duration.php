<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;

use function abs;
use function intdiv;
use function is_float;
use function preg_match;
use function round;
use function rtrim;
use function str_pad;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const STR_PAD_LEFT;

final readonly class Duration
{
    private const int MICRO_PER_SECOND = 1_000_000;
    private const int MICRO_PER_MINUTE = 60 * self::MICRO_PER_SECOND;
    private const int MICRO_PER_HOUR = 60 * self::MICRO_PER_MINUTE;
    private const int MICRO_PER_DAY = 24 * self::MICRO_PER_HOUR;
    private const string DURATION_PATTERN = '/^
        (?<sign>[+-])?
        P
        (?=.*?(?:\d+W|\d+D|T\d+H|T\d+M|T\d+(?:\.\d+)?S)) # look-ahead to restrict suppport for ISO8601 formats
        (?:(?<weeks>\d+)W)?
        (?:(?<days>\d+)D)?
        (?:T
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:(?<seconds>\d+(?:\.\d+)?)S)?
        )?
    $/x';

    public int $hours;
    public int $minutes;
    public int $seconds;
    public int $microseconds;
    public bool $inverted;

    /**
     * @param int $value expressed in microseconds
     *
     * @throws InvalidDuration
     */
    private function __construct(private int $value)
    {
        ($value > PHP_INT_MIN + 1 && $value < PHP_INT_MAX) || throw InvalidDuration::dueToOverflow();

        $this->inverted = $this->value < 0;

        $microseconds = abs($this->value);
        $this->hours = intdiv($microseconds, self::MICRO_PER_HOUR);

        $microseconds %= self::MICRO_PER_HOUR;
        $this->minutes = intdiv($microseconds, self::MICRO_PER_MINUTE);

        $microseconds %= self::MICRO_PER_MINUTE;
        $this->seconds = intdiv($microseconds, self::MICRO_PER_SECOND);
        $this->microseconds = $microseconds % self::MICRO_PER_SECOND;
    }

    /**
     * @throws InvalidDuration if the value can not be inverted
     */
    public static function of(
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $microseconds = 0,
    ): self {
        return new self(self::toMicroseconds(0, $hours, $minutes, $seconds, $microseconds));
    }

    private static function toMicroseconds(
        int $days,
        int $hours,
        int $minutes,
        int|float $seconds,
        int $microseconds
    ): int {
        $seconds = is_float($seconds)
            ? (int) round($seconds * self::MICRO_PER_SECOND)
            : ($seconds * self::MICRO_PER_SECOND);

        return ($days * self::MICRO_PER_DAY)
            + ($hours * self::MICRO_PER_HOUR)
            + ($minutes * self::MICRO_PER_MINUTE)
            + $seconds
            + $microseconds;
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
    public static function fromIso8601(string $value): self
    {
        1 === preg_match(self::DURATION_PATTERN, $value, $parts) || throw InvalidDuration::dueToMalformedIso8601($value);

        $microseconds = self::toMicroseconds(
            days: (((int) ($parts['weeks'] ?? 0) * 7) + (int) ($parts['days'] ?? 0)),
            hours: (int) ($parts['hours'] ?? 0),
            minutes: (int) ($parts['minutes'] ?? 0),
            seconds: (float) ($parts['seconds'] ?? 0),
            microseconds: 0
        );

        return self::of(microseconds: '-' === ($parts['sign'] ?? '-') ? -$microseconds : $microseconds);
    }

    /**
     * @throws InvalidDuration
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * @throws InvalidDuration
     */
    public static function max(): self
    {
        return new self(PHP_INT_MAX - 1);
    }

    /**
     * @throws InvalidDuration
     */
    public static function min(): self
    {
        return new self(PHP_INT_MIN + 2);
    }

    /**
     * Returns the string representation of the Duration.
     *
     * The following format is used [-]HH:MM:SS[.mmmmmm]
     * the fraction and the signed are only display if
     * they duration is negative and/or the sub seconds
     * fraction is different from 0
     */
    public function toClockFormat(): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);

        $formatted = $this->hours.':'.$pad($this->minutes, 2).':'.$pad($this->seconds, 2);
        if (0 !== $this->microseconds) {
            $formatted .= '.'.$pad($this->microseconds, 6);
        }

        return $this->inverted ? '-'.$formatted : $formatted;
    }

    /**
     * Returns the ISO8601 string representation of the duration.
     *
     * - fractional values are only allowed on seconds
     * - only D, H, M and S are allowed; M represents the minutes
     * - negative marker is allowed in front of the expression
     */
    public function toIso8601(): string
    {
        $seconds = (string) $this->seconds;
        if (0 !== $this->microseconds) {
            $seconds .= '.'.rtrim(
                str_pad((string) $this->microseconds, 6, '0', STR_PAD_LEFT),
                '0'
            );
        }

        $days = intdiv($this->hours, 24);
        $hours = $this->hours % 24;

        $time = '';
        if (0 !== $hours) {
            $time .= $hours.'H';
        }

        if (0 !== $this->minutes) {
            $time .= $this->minutes.'M';
        }

        if ('0' !== $seconds) {
            $time .= $seconds.'S';
        }

        return  (0 === $days && '' === $time)
            ? 'PT0S'
            : ($this->inverted ? '-' : '').'P'.(0 !== $days ? $days.'D' : '').('' !== $time ? 'T'.$time : '');
    }

    public function toDateInterval(): DateInterval
    {
        $days = intdiv($this->hours, 24);
        $hours = $this->hours % 24;

        $interval = new DateInterval('PT0S');
        $interval->d = $days;
        $interval->h = $hours;
        $interval->i = $this->minutes;
        $interval->s = $this->seconds;
        if (0 !== $this->microseconds) {
            $interval->f = $this->microseconds / self::MICRO_PER_SECOND;
        }
        $interval->invert = $this->inverted ? 1 : 0;

        return $interval;
    }

    public function toMicro(): int
    {
        return $this->value;
    }

    /**
     * Returns true when the duration is zero, false otherwise.
     */
    public function isEmpty(): bool
    {
        return 0 === $this->value;
    }

    /**
     * @throws InvalidDuration
     */
    public function negate(): self
    {
        return new self(-$this->value);
    }

    /**
     * @throws InvalidDuration
     */
    public function abs(): self
    {
        return $this->value < 0 ? $this->negate() : $this;
    }

    /**
     * @throws InvalidDuration
     */
    public function truncateTo(Precision $precision): self
    {
        $value = match ($precision) {
            Precision::Seconds => ($this->hours * self::MICRO_PER_HOUR) + ($this->minutes * self::MICRO_PER_MINUTE) + ($this->seconds * self::MICRO_PER_SECOND),
            Precision::Minutes => ($this->hours * self::MICRO_PER_HOUR) + ($this->minutes * self::MICRO_PER_MINUTE),
            Precision::Hours => ($this->hours * self::MICRO_PER_HOUR),
        };

        return new self($this->inverted ? -$value : $value);
    }

    /**
     * @throws InvalidDuration
     */
    public function sum(self ...$other): self
    {
        $value = 0;
        foreach ($other as $duration) {
            $value += $duration->value;
        }

        return 0 === $value ? $this : new self($this->value + $value);
    }

    /**
     * @throws InvalidDuration if the value can not be inverted
     */
    public function increment(int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): self
    {
        return $this->sum(self::of(hours: $hours, minutes: $minutes, seconds: $seconds, microseconds: $microseconds));
    }

    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    public function isGreaterThan(self $other): bool
    {
        return 0 < $this->compareTo($other);
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return 0 <= $this->compareTo($other);
    }
    public function isLessThan(self $other): bool
    {
        return 0 > $this->compareTo($other);
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return 0 >= $this->compareTo($other);
    }
}
