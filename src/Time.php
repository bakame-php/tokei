<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeImmutable;
use DateTimeInterface;

use function abs;
use function intdiv;
use function preg_match;
use function preg_quote;
use function str_pad;
use function str_replace;
use function strlen;
use function substr;
use function trim;

use const STR_PAD_LEFT;

final readonly class Time
{
    private const int MICRO_PER_SECOND = 1_000_000;
    private const int MICRO_PER_MINUTE = 60 * self::MICRO_PER_SECOND;
    private const int MICRO_PER_HOUR  = 60 * self::MICRO_PER_MINUTE;
    private const int MICRO_PER_DAY = 24 * self::MICRO_PER_HOUR;
    private const string TIME_PATTERN = '/^
        (?<hour>\d{1,2}){{SEP}}
        (?<minute>\d{1,2})({{SEP}}
        (?<second>\d{1,2}))?
        (?:\.(?<micro>\d{1,6}))?
    $/x';

    private int $value;
    public int $hour;
    public int $minute;
    public int $second;
    public int $microsecond;

    /**
     * @param int $value represents the microseconds from midnight
     */
    private function __construct(int $value)
    {
        $this->value = (($value % self::MICRO_PER_DAY) + self::MICRO_PER_DAY) % self::MICRO_PER_DAY;

        $microseconds = abs($this->value);
        $this->hour = intdiv($microseconds, self::MICRO_PER_HOUR);

        $microseconds %= self::MICRO_PER_HOUR;
        $this->minute = intdiv($microseconds, self::MICRO_PER_MINUTE);

        $microseconds %= self::MICRO_PER_MINUTE;
        $this->second = intdiv($microseconds, self::MICRO_PER_SECOND);
        $this->microsecond = $microseconds % self::MICRO_PER_SECOND;
    }

    /**
     * @throws InvalidTime
     */
    public static function at(
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        int $microsecond = 0,
    ): self {
        ($hour >= 0 && $hour < 24) || throw InvalidTime::dueToMalformedHour($hour);
        ($minute >= 0 && $minute < 60) || throw InvalidTime::dueToMalformedMinute($minute);
        ($second >= 0 && $second < 60) || throw InvalidTime::dueToMalformedSecond($second);
        ($microsecond >= 0 && $microsecond < self::MICRO_PER_SECOND) || throw InvalidTime::dueToMalformedMicrosecond($microsecond);

        return new self(
            ($hour * self::MICRO_PER_HOUR)
            + ($minute * self::MICRO_PER_MINUTE)
            + ($second * self::MICRO_PER_SECOND)
            + $microsecond
        );
    }

    /**
     * @throws InvalidTime
     */
    private static function assertValidSeparator(string $separator): void
    {
        (1 === strlen($separator) && !ctype_digit($separator)) || throw InvalidTime::dueToInvalidSeparator($separator);
    }

    /**
     * @throws InvalidTime
     */
    public static function extractFrom(DateTimeInterface $dateTime): self
    {
        return self::at(
            (int) $dateTime->format('H'),
            (int) $dateTime->format('i'),
            (int) $dateTime->format('s'),
            (int) $dateTime->format('u'),
        );
    }

    /**
     * @throws InvalidTime
     */
    public static function parse(string $value, string $separator = ':'): ?self
    {
        self::assertValidSeparator($separator);

        $value = trim($value);
        $escaped = preg_quote($separator, '/');
        if (1 !== preg_match(str_replace('{{SEP}}', $escaped, self::TIME_PATTERN), $value, $parts)) {
            return null;
        }

        $hour = (int) $parts['hour'];
        $minute = (int) ($parts['minute'] ?? 0);
        $second = (int) ($parts['second'] ?? 0);
        $micro = isset($parts['micro']) ? (int) str_pad(substr($parts['micro'], 0, 6), 6, '0') : 0;

        return ($hour > 23 || $minute > 59 || $second > 59)
            ? null
            : self::at($hour, $minute, $second, $micro);
    }

    public static function min(): self
    {
        return new self(0);
    }

    public static function max(): self
    {
        return new self(-1);
    }

    public static function noon(): self
    {
        return new self(12 * self::MICRO_PER_HOUR);
    }

    public static function midnight(): self
    {
        return self::min();
    }

    /**
     * @param int $value expressed in microsecond since midnight
     */
    public static function atMicroOfDay(int $value): self
    {
        return new self($value);
    }

    /**
     * @param int $value expressed in milliseconds since midnight
     */
    public static function atMilliOfDay(int $value): self
    {
        return new self($value * 1_000);
    }

    /**
     * @param int $value expressed in seconds since midnight
     */
    public static function atSecondOfDay(int $value): self
    {
        return new self($value * self::MICRO_PER_SECOND);
    }

    /**
     * @param int $value expressed in minutes since midnight
     */
    public static function atMinuteOfDay(int $value): self
    {
        return new self($value * self::MICRO_PER_MINUTE);
    }

    public function toMicroOfDay(): int
    {
        return $this->value;
    }

    /**
     * @throws InvalidTime
     */
    public function format(
        string $separator = ':',
        PaddingMode $padding = PaddingMode::Padded,
        SubSecondDisplay $format = SubSecondDisplay::Auto,
    ): string {
        $pad = static fn (int $v) => PaddingMode::Padded === $padding
            ? str_pad((string) $v, 2, '0', STR_PAD_LEFT)
            : (string) $v;

        self::assertValidSeparator($separator);

        $base = $pad($this->hour).$separator.$pad($this->minute).$separator.$pad($this->second);
        $includeSubSeconds = match ($format) {
            SubSecondDisplay::Always => true,
            SubSecondDisplay::Never => false,
            SubSecondDisplay::Auto => 0 !== $this->microsecond,
        };

        return ! $includeSubSeconds
            ? $base
            : $base.'.'.str_pad((string) $this->microsecond, 6, '0', STR_PAD_LEFT);
    }

    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function isBefore(self $other): bool
    {
        return -1 === $this->compareTo($other);
    }

    public function isBeforeOrEqual(self $other): bool
    {
        return 0 >= $this->compareTo($other);
    }

    public function isAfter(self $other): bool
    {
        return 1 === $this->compareTo($other);
    }

    public function isAfterOrEqual(self $other): bool
    {
        return 0 <= $this->compareTo($other);
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    public function add(Duration $duration): self
    {
        $durationAsMicroseconds = $duration->toMicro();

        return 0 === $durationAsMicroseconds ? $this : new self($this->value + $durationAsMicroseconds);
    }

    /**
     * @throws InvalidTime
     */
    public function with(
        ?int $hour = null,
        ?int $minute = null,
        ?int $second = null,
        ?int $microsecond = null
    ): self {
        $hour ??= $this->hour;
        $minute ??= $this->minute;
        $second ??= $this->second;
        $microsecond ??= $this->microsecond;

        return $hour === $this->hour
            && $minute === $this->minute
            && $second === $this->second
            && $microsecond === $this->microsecond
            ? $this : self::at($hour, $minute, $second, $microsecond);
    }

    public function applyTo(DateTimeInterface $date): DateTimeImmutable
    {
        if (!$date instanceof DateTimeImmutable) {
            $date = DateTimeImmutable::createFromInterface($date);
        }

        return $date->setTime($this->hour, $this->minute, $this->second, $this->microsecond);
    }

    /**
     * @throws InvalidDuration
     */
    public function diff(self $other): Duration
    {
        return Duration::of(microseconds: $other->value - $this->value);
    }

    /**
     * @throws InvalidDuration
     */
    public function distance(self $other): Duration
    {
        return Duration::of(microseconds: (($other->value - $this->value) % self::MICRO_PER_DAY + self::MICRO_PER_DAY) % self::MICRO_PER_DAY);
    }
}
