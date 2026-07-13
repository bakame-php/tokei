<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArgumentCountError;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;

use function array_key_first;
use function array_key_last;
use function preg_match;
use function str_pad;
use function substr;
use function trim;
use function usort;

final class Time implements JsonSerializable
{
    private const string REGEXP_ISO8601 = '@^
        (?<hour>\d{1,2}):
        (?<minute>\d{1,2})
        (:(?<second>\d{1,2}))?
        (?:\.(?<microsecond>\d{1,6}))?
    $@x';

    private const string REGEXP_COMPACT = '@^
        (?<hour>\d{1,2})\s*h\s*
        (?<minute>\d{1,2})\s*m\s*
        (?:(?<second>\d{1,2})\s*s\s*)?
        (?:
            (?<fvalue>\d{1,6})\s*
            (?<funit>µs|us|ms)\s*
        )?
    $@x';

    private ?DurationParts $parts = null;
    /** @var non-negative-int */
    public readonly int $totalMicroseconds;

    public int|float $totalMilliseconds { get => $this->in(Unit::Microsecond); }
    public int|float $totalSeconds { get => $this->in(Unit::Second); }
    public int|float $totalMinutes { get => $this->in(Unit::Minute); }
    public int|float $totalHours { get => $this->in(Unit::Hour); }

    public int $hour { get => $this->parts()->hours; }
    public int $minute { get => $this->parts()->minutes; }
    public int $second { get => $this->parts()->seconds; }
    public int $microsecond { get => $this->parts()->microseconds; }

    /**
     * @param int $ticks Time since midnight expressed in the library base unit
     */
    private function __construct(int $ticks)
    {
        $this->totalMicroseconds = UnitTransformer::wrap($ticks, Unit::Day);
    }

    private function parts(): DurationParts
    {
        return $this->parts ??= DurationParts::parse($this->totalMicroseconds);
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
        ($hour >= 0 && $hour < 24) || throw InvalidTime::dueToMalformedTime($hour, Unit::Hour);
        ($minute >= 0 && $minute < 60) || throw InvalidTime::dueToMalformedTime($minute, Unit::Minute);
        ($second >= 0 && $second < 60) || throw InvalidTime::dueToMalformedTime($second, Unit::Second);
        ($microsecond >= 0 && $microsecond < 1_000_000) || throw InvalidTime::dueToMalformedTime($microsecond, Unit::Microsecond);

        return new self(new DurationParts(
            hours: $hour,
            minutes: $minute,
            seconds: $second,
            microseconds: $microsecond,
            sign: 1,
        )->build());
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
     * @throws InvalidTime
     */
    public static function fromFormat(string $value, TimeFormat $format): self
    {
        $regexp = match ($format) {
            TimeFormat::Iso8601Extended => self::REGEXP_ISO8601,
            TimeFormat::Compact => self::REGEXP_COMPACT,
        };

        $notation = trim($value);
        1 === preg_match($regexp, $notation, $parts) || throw new InvalidTime('Unknown or bad format `'.$value.'`'.'`.');

        if (self::REGEXP_ISO8601 === $regexp) {
            return Time::at(
                hour: (int) $parts['hour'],
                minute: (int) $parts['minute'],
                second: (int) ($parts['second'] ?? 0),
                microsecond: (int) str_pad(substr($parts['microsecond'] ?? '0', 0, 6), 6, '0'),
            );
        }

        $fractionValue = (int) ($parts['fvalue'] ?? 0);
        $fractionUnit = $parts['funit'] ?? 'µs';
        if ('ms' === $fractionUnit) {
            ($fractionValue <= 999) || throw new InvalidDuration('millisecond fraction value cannot be greater than 999.');
            $fractionValue *= 1000;
        }

        return Time::at(
            hour: (int) $parts['hour'],
            minute: (int) $parts['minute'],
            second: (int) ($parts['second'] ?? 0),
            microsecond: $fractionValue,
        );
    }

    /**
     * Returns a new instance from a number of unit of time since midnight.
     */
    public static function sinceMidnight(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): self
    {
        return new self(InputNormalizer::duration($duration)->totalMicroseconds);
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
     */
    public static function now(DateTimeInterface|DateTimeZone|string $timezone): self
    {
        return self::fromDateTime(new DateTimeImmutable(timezone: InputNormalizer::timezone($timezone)));
    }

    /**
     * Returns the smallest instances among the given values.
     */
    public static function minOf(self ...$times): self
    {
        [] !== $times || throw new ArgumentCountError('minOf() expects at least one time');
        usort($times, Time::compare(...));

        return $times[array_key_first($times)];
    }

    /**
     * Returns the highest instances among the given values.
     */
    public static function maxOf(self ...$times): self
    {
        [] !== $times || throw new ArgumentCountError('maxOf() expects at least one time');
        usort($times, Time::compare(...));

        return $times[array_key_last($times)];
    }

    /**
     * @return array{0: array{total_microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['total_microseconds' => $this->totalMicroseconds], []];
    }

    /**
     * @param array{0: array{total_microseconds: int}, 1:array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->totalMicroseconds = new self($properties['total_microseconds'])->totalMicroseconds;
    }

    /**
     * Returns the time as the number of unit of time since midnight.
     */
    public function in(Unit $unit): int|float
    {
        return UnitTransformer::fromMicroseconds($this->totalMicroseconds, $unit);
    }

    /**
     * Encodes a Time into a specified string notation representation.
     *
     * @return non-empty-string
     */
    public function format(TimeFormat $format): string
    {
        return $this->parts()->format(
            format: match ($format) {
                TimeFormat::Iso8601Extended => DurationFormat::Timer,
                TimeFormat::Compact => DurationFormat::Compact,
            },
            compactType: DurationParts::COMPACT_TIME
        );
    }

    /**
     * @param non-empty-string $locale
     * @param DateTimeInterface|DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException
     *@see LocaleTimeFormatter::format()
     *
     */
    public function toLocaleString(
        string $locale,
        DateTimeInterface|DateTimeZone|string $timezone = 'UTC',
        LocaleVerbosity $verbosity = LocaleVerbosity::Medium
    ): string {
        return new LocaleTimeFormatter(locale: $locale, timezone: $timezone, verbosity: $verbosity)->format($this);
    }

    /**
     * Returns the DateTimeImmutable instance for the current time in a given timezone.
     *
     * @param DateTimeInterface|DateTimeZone|non-empty-string $timeZone
     *
     * @throws TimeException
     */
    public function toDateTime(DateTimeInterface|DateTimeZone|string $timeZone): DateTimeImmutable
    {
        return $this->applyTo(new DateTimeImmutable(timezone: InputNormalizer::timezone($timeZone)));
    }

    /**
     * @see self::format()
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format(TimeFormat::Iso8601Extended);
    }

    /**
     * Compare this instance with another.
     *
     * @throws InvalidTime
     *
     * @return int<-1, 1> If this time is before, on, or after the given time.
     */
    public static function compare(
        Time|Event|NativeEvent|DateTimeInterface $that,
        Time|Event|NativeEvent|DateTimeInterface $other
    ): int {
        return InputNormalizer::time($that)->totalMicroseconds <=> InputNormalizer::time($other)->totalMicroseconds;
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

    public function equals(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 === Time::compare($this, $other);
    }

    public function isAfterOrEqual(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 <= Time::compare($this, $other);
    }

    public function isAfter(Time|Event|NativeEvent|DateTimeInterface $other): bool
    {
        return 0 < Time::compare($this, $other);
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
        $min = InputNormalizer::time($min);
        $max = InputNormalizer::time($max);
        $max->isAfterOrEqual($min) || throw new InvalidTime('The maximum time must be after or equal to the minimum time.');

        return match (true) {
            $this->isBefore($min) => $min,
            $this->isAfter($max) => $max,
            default => $this,
        };
    }

    /**
     * Alter the time by adding a duration.
     *
     * The duration will be added or subtract depending on its sign.
     *
     * @throws InvalidDuration
     */
    public function add(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): self
    {
        $duration = InputNormalizer::duration($duration);

        return $duration->isZero() ? $this : self::sinceMidnight(Duration::of(microseconds: $this->totalMicroseconds)->add($duration));
    }

    /**
     * Alter the time by subtracting a duration object.
     *
     * The duration will be added or subtract depending on its sign.
     *
     * @throws InvalidDuration
     */
    public function sub(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): self
    {
        $duration = InputNormalizer::duration($duration);

        return $duration->isZero() ? $this : self::sinceMidnight(Duration::of(microseconds: $this->totalMicroseconds)->sub($duration));
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
        $rounded = UnitTransformer::round($this->totalMicroseconds, $unit, $mode);

        return $this->totalMicroseconds === $rounded ? $this : new self($rounded);
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
     * @throws InvalidDuration|TimeException
     */
    public function diff(Time|Event|NativeEvent|DateTimeInterface $other): Duration
    {
        $duration = InputNormalizer::time($other)->totalMicroseconds - $this->totalMicroseconds;

        return 0 > $duration
            ? Duration::of(microseconds: -$duration)->negated()
            : Duration::of(microseconds: $duration);
    }

    /**
     * Returns the forward cyclic difference (24 wrap) between this instance and a specified time.
     *
     * @throws InvalidDuration|TimeException
     */
    public function distance(Time|Event|NativeEvent|DateTimeInterface $other): Duration
    {
        return Duration::of(microseconds: UnitTransformer::wrap(
            InputNormalizer::time($other)->totalMicroseconds - $this->totalMicroseconds,
            Unit::Day
        ));
    }
}
