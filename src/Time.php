<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use Throwable;
use ValueError;

use function is_int;

final readonly class Time implements JsonSerializable
{
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
        $this->value = UnitTransformer::wrap($value, Unit::Day);
        $microseconds = $this->value;
        $this->hour = UnitTransformer::whole($microseconds, Unit::Hour);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Hour);
        $this->minute = UnitTransformer::whole($microseconds, Unit::Minute);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Minute);
        $this->second = UnitTransformer::whole($microseconds, Unit::Second);
        $this->microsecond = UnitTransformer::remainder($microseconds, Unit::Second);
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
        ($microsecond >= 0 && $microsecond < 1_000_000) || throw InvalidTime::dueToMalformedMicrosecond($microsecond);

        return new self(
            UnitTransformer::toMicroseconds($hour, Unit::Hour)
            + UnitTransformer::toMicroseconds($minute, Unit::Minute)
            + UnitTransformer::toMicroseconds($second, Unit::Second)
            + $microsecond
        );
    }

    /**
     * @param DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException if the timezone identifier is invalid
     */
    private static function filterTimezone(DateTimeZone|string $timezone): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Throwable $exception) {
            throw TimeException::invalidTimezone(timezone: $timezone, previous: $exception);
        }
    }

    /**
     * Returns a new instance from a DateTimeInterface object.
     *
     * @throws InvalidTime
     */
    public static function fromDateTime(DateTimeInterface $datetime): self
    {
        return self::at(
            (int) $datetime->format('H'),
            (int) $datetime->format('i'),
            (int) $datetime->format('s'),
            (int) $datetime->format('u'),
        );
    }

    /**
     * @see TimeFormat::decode()
     *
     * @throws InvalidTime
     */
    public static function fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601): self
    {
        return $format->decode($value);
    }

    /**
     * Returns a new instance from a number of unit of time since midnight.
     */
    public static function fromOffset(int|float $value, Unit $unit): self
    {
        return new self(UnitTransformer::toMicroseconds($value, $unit));
    }

    public static function midnight(): self
    {
        /** @var ?self $time */
        static $time = null;

        return $time ??= new self(0);
    }

    public static function noon(): self
    {
        /** @var ?self $time */
        static $time = null;

        return $time ??= new self(UnitTransformer::toMicroseconds(12, Unit::Hour));
    }

    public static function endOfDay(): self
    {
        /** @var ?self $time */
        static $time = null;

        return $time ??= new self(-1);
    }

    /**
     * Returns the current time in UTC.
     */
    public static function utc(): self
    {
        return self::now('UTC');
    }

    /**
     * Returns the current time in the given time-zone.
     *
     * @param DateTimeZone|non-empty-string $timezone
     *
     * @throws InvalidTime|TimeException
     */
    public static function now(DateTimeZone|string $timezone): self
    {
        return self::fromDateTime(new DateTimeImmutable(timezone: self::filterTimezone($timezone)));
    }

    /**
     * Returns the smallest instances among the given values.
     *
     * @throws ValueError if no value given
     */
    public static function minOf(self ...$times): self
    {
        $value = null;
        foreach ($times as $time) {
            if (null === $value || $time->isBefore($value)) {
                $value = $time;
            }
        }

        return null !== $value ? $value : throw new ValueError('minOf() expects at least one time');
    }

    /**
     * Returns the highest instances among the given values.
     *
     * @throws InvalidTime
     */
    public static function maxOf(self ...$times): self
    {
        $value = null;
        foreach ($times as $time) {
            if (null === $value || $time->isAfter($value)) {
                $value = $time;
            }
        }

        return null !== $value ? $value : throw new ValueError('maxOf() expects at least one time');
    }

    /**
     * Returns the time as the number of unit of time since midnight.
     */
    public function toOffset(Unit $unit): int|float
    {
        return UnitTransformer::fromMicroseconds($this->value, $unit);
    }

    /**
     * @see TimeFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(TimeFormat $format = TimeFormat::Iso8601): string
    {
        return $format->encode($this);
    }

    /**
     * @see LocaleTimeFormatter::format()
     *
     * @param non-empty-string $locale
     * @param DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException
     */
    public function toLocaleString(
        string $locale,
        DateTimeZone|string $timezone = 'UTC',
        LocaleVerbosity $verbosity = LocaleVerbosity::Medium
    ): string {
        return (new LocaleTimeFormatter(locale: $locale, timezone: $timezone, verbosity: $verbosity))->format($this);
    }

    /**
     * Returns the DateTimeImmutable instance for the current time in a given timezone.
     *
     * @param DateTimeZone|non-empty-string $timeZone
     *
     * @throws TimeException
     */
    public function toDateTime(DateTimeZone|string $timeZone): DateTimeImmutable
    {
        return $this->applyTo(new DateTimeImmutable(timezone: self::filterTimezone($timeZone)));
    }

    /**
     * @see self::format()
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * Compare this instance with another.
     *
     * @return int<-1, 1> If this time is before, on, or after the given time.
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    /**
     * Tells whether this instance is less than the specified time.
     */
    public function isBefore(self $other): bool
    {
        return 0 > $this->compareTo($other);
    }

    /**
     * Tells whether this instance is less than or equal the specified time.
     */
    public function isBeforeOrEqual(self $other): bool
    {
        return 0 >= $this->compareTo($other);
    }

    public function isAfter(self $other): bool
    {
        return 0 < $this->compareTo($other);
    }

    public function isAfterOrEqual(self $other): bool
    {
        return 0 <= $this->compareTo($other);
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    /**
     * Checks if this instance is within a certain bound.
     *
     * If the value is in range it returns the value, if the value is not in range it returns the nearest bound.
     *
     * @throws InvalidTime
     */
    public function clamp(self $min, self $max): self
    {
        $max->isAfterOrEqual($min) || throw new InvalidTime('The maximum time must be after or equal to the minimum time.');

        return match (true) {
            $this->isBefore($min) => $min,
            $this->isAfter($max) => $max,
            default => $this,
        };
    }

    /**
     * Alter the time by using a duration object.
     *
     * The duration will be added or subtract depending on its sign.
     *
     * @throws InvalidDuration
     */
    public function shift(Duration $duration): self
    {
        if ($duration->isZero()) {
            return $this;
        }

        $value = $this->value + $duration->total(Unit::Microsecond);
        is_int($value) || throw InvalidDuration::dueToOverflow();

        return new self($value);
    }

    /**
     * Returns a new instance of this Time with their properties altered if specified and different.
     *
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

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, Rounding $mode = Rounding::Nearest): self
    {
        $rounded = UnitTransformer::round($this->value, $unit, $mode);

        return $this->value === $rounded ? $this : new self($rounded);
    }

    /**
     * Returns a new DateTimeImmutable instance on which the current time is applied.
     */
    public function applyTo(DateTimeInterface $datetime): DateTimeImmutable
    {
        if (!$datetime instanceof DateTimeImmutable) {
            $datetime = DateTimeImmutable::createFromInterface($datetime);
        }

        return $datetime->setTime($this->hour, $this->minute, $this->second, $this->microsecond);
    }

    /**
     * Returns the signed difference between this instance and a specified time.
     *
     * @throws InvalidDuration
     */
    public function diff(self $other): Duration
    {
        $duration = $other->value - $this->value;

        return 0 > $duration
            ? Duration::of(microseconds: -$duration)->negated()
            : Duration::of(microseconds: $duration);
    }

    /**
     * Returns the forward cyclic difference (24 wrap) between this instance and a specified time.
     *
     * @throws InvalidDuration
     */
    public function distance(self $other): Duration
    {
        /** @var non-negative-int $duration */
        $duration = UnitTransformer::wrap($other->value - $this->value, Unit::Day);

        return Duration::of(microseconds: $duration);
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['microseconds' => (int) $this->toOffset(Unit::Microsecond)], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1:array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $time = new self($properties['microseconds']);
        $this->value = $time->value;
        $this->hour = $time->hour;
        $this->minute = $time->minute;
        $this->second = $time->second;
        $this->microsecond = $time->microsecond;
    }
}
