<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use Throwable;
use ValueError;

use function is_int;

final readonly class Time implements JsonSerializable
{
    public int $ticks;
    public int $hour;
    public int $minute;
    public int $second;
    public int $microsecond;

    /**
     * @param int $ticks represents the microseconds from midnight
     */
    private function __construct(int $ticks)
    {
        $this->ticks = UnitTransformer::wrap($ticks, Unit::Day);
        $parts = UnitTransformer::decompose($this->ticks);
        $this->hour = $parts->hours;
        $this->minute = $parts->minutes;
        $this->second = $parts->seconds;
        $this->microsecond = $parts->microseconds;
    }

    private static function extractDuration(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $that): Duration
    {
        return match (true) {
            $that instanceof DateInterval => Duration::fromDateInterval($that),
            $that instanceof NativeInterval => Interval::fromNative($that)->duration,
            $that instanceof NativeTask => Task::fromNative($that)->interval->duration,
            $that instanceof Task => $that->interval->duration,
            $that instanceof Interval => $that->duration,
            $that instanceof Duration => $that,
        };
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

        return new self(UnitTransformer::compose(
            days: 0,
            hours: $hour,
            minutes: $minute,
            seconds: $second,
            microseconds: $microsecond,
            sign: 1
        ));
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
            throw TimeException::dueToInvalidTimezone(timezone: $timezone, previous: $exception);
        }
    }

    private static function extractTime(Time|Event|NativeEvent|DateTimeInterface $time): self
    {
        return match (true) {
            $time instanceof Event => $time->at,
            $time instanceof NativeEvent => Event::fromNative($time)->at,
            $time instanceof DateTimeInterface => Time::fromDateTime($time),
            default => $time,
        };
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
    public function in(Unit $unit): int|float
    {
        return UnitTransformer::fromMicroseconds($this->ticks, $unit);
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
    public static function compare(
        Time|Event|NativeEvent|DateTimeInterface $that,
        Time|Event|NativeEvent|DateTimeInterface $other
    ): int {
        return self::extractTime($that)->ticks <=> self::extractTime($other)->ticks;
    }

    /**
     * Tells whether this instance is less than the specified time.
     */
    public function isBefore(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 > Time::compare($this, $other);
    }

    /**
     * Tells whether this instance is less than or equal the specified time.
     */
    public function isBeforeOrEqual(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 >= Time::compare($this, $other);
    }

    public function isAfter(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 < Time::compare($this, $other);
    }

    public function isAfterOrEqual(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 <= Time::compare($this, $other);
    }

    public function equals(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 === Time::compare($this, $other);
    }

    /**
     * Checks if this instance is within a certain bound.
     *
     * If the value is in range it returns the value, if the value is not in range it returns the nearest bound.
     *
     * @throws InvalidTime
     */
    public function clamp(Time|Event|NativeEvent|DateTimeInterface $min, Time|Event|NativeEvent|DateTimeInterface $max): self
    {
        $min = self::extractTime($min);
        $max = self::extractTime($max);
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
    public function shift(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): self
    {
        $duration = self::extractDuration($duration);
        if ($duration->isZero()) {
            return $this;
        }

        $value = $this->ticks + $duration->microseconds;
        is_int($value) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

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
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $rounded = UnitTransformer::round($this->ticks, $unit, $mode);

        return $this->ticks === $rounded ? $this : new self($rounded);
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
    public function diff(Time|Event|NativeEvent|DateTimeInterface $other): Duration
    {
        $duration = self::extractTime($other)->ticks - $this->ticks;

        return 0 > $duration
            ? Duration::of(microseconds: -$duration)->negated()
            : Duration::of(microseconds: $duration);
    }

    /**
     * Returns the forward cyclic difference (24 wrap) between this instance and a specified time.
     *
     * @throws InvalidDuration
     */
    public function distance(Time|Event|NativeEvent|DateTimeInterface $other): Duration
    {
        /** @var non-negative-int $duration */
        $duration = UnitTransformer::wrap(self::extractTime($other)->ticks - $this->ticks, Unit::Day);

        return Duration::of(microseconds: $duration);
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['microseconds' => $this->ticks], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1:array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $time = new self($properties['microseconds']);
        $this->ticks = $time->ticks;
        $this->hour = $time->hour;
        $this->minute = $time->minute;
        $this->second = $time->second;
        $this->microsecond = $time->microsecond;
    }
}
