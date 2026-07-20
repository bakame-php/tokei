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
use function array_map;
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
    private readonly int $ticks;
    public int $hour { get => $this->parts()->hour; }
    public int $minute { get => $this->parts()->minute; }
    public int $second { get => $this->parts()->second; }
    public int $microsecond { get => $this->parts()->microsecond; }

    /**
     * @param int $ticks Time since midnight expressed in the library base unit
     */
    private function __construct(int $ticks)
    {
        $this->ticks = UnitTransformer::wrap($ticks, Unit::Day);
    }

    /**
     * @return array{0: array{total_microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['total_microseconds' => $this->ticks], []];
    }

    /**
     * @param array{0: array{total_microseconds: int}, 1:array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->ticks = new self($properties['total_microseconds'])->ticks;
    }

    private function parts(): DurationParts
    {
        return $this->parts ??= DurationParts::parse($this->ticks);
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
            hour: $hour,
            minute: $minute,
            second: $second,
            microsecond: $microsecond,
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
            TimeFormat::Clock => self::REGEXP_ISO8601,
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
    public static function sinceMidnight(Duration|DateInterval|Interval|Task $duration): self
    {
        return new self(
            (int) InputNormalizer::duration($duration)
                ->modulo(Duration::fullDay())
                ->in(Unit::Microsecond)
        );
    }

    public static function midnight(): self
    {
        return new self(0);
    }

    public static function noon(): self
    {
        return self::sinceMidnight(Duration::of(hours: 12));
    }

    public static function endOfDay(): self
    {
        return new self(-1);
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
     * @param DateTimeInterface|DateTimeZone|non-empty-string $timezone
     */
    public static function now(DateTimeInterface|DateTimeZone|string $timezone): self
    {
        return self::fromDateTime(new DateTimeImmutable(timezone: InputNormalizer::timezone($timezone)));
    }

    /**
     * Returns the smallest instances among the given values.
     */
    public static function minOf(Time|Event|DateTimeInterface ...$times): self
    {
        [] !== $times || throw new ArgumentCountError('minOf() expects at least one time');

        $times = array_map(InputNormalizer::time(...), $times);
        usort($times, Duration::compare(...));

        return $times[array_key_first($times)];
    }

    /**
     * Returns the highest instances among the given values.
     */
    public static function maxOf(Time|Event|DateTimeInterface ...$times): self
    {
        [] !== $times || throw new ArgumentCountError('maxOf() expects at least one time');

        $times = array_map(InputNormalizer::time(...), $times);
        usort($times, Duration::compare(...));

        return $times[array_key_last($times)];
    }

    /**
     * Encodes a Time into a specified string notation representation.
     *
     * @return non-empty-string
     */
    public function format(TimeFormat $format): string
    {
        return $this->parts()->toTimeString($format);
    }

    /**
     * @param non-empty-string $locale
     * @param DateTimeInterface|DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException
     * @see LocaleTimeFormatter::format()
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
     * @throws TokeiException
     */
    public function toDateTime(DateTimeInterface|DateTimeZone|string $timeZone): DateTimeImmutable
    {
        return $this->applyTo(new DateTimeImmutable(timezone: InputNormalizer::timezone($timeZone)));
    }

    /**
     * Returns the duration offset from midnight.
     *
     * @throws TokeiException
     */
    public function offset(): Duration
    {
        return Duration::of(microseconds: $this->ticks);
    }

    /**
     * @see self::format()
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format(TimeFormat::Clock);
    }

    public function isBefore(Time|Event|DateTimeInterface $other): bool
    {
        return 0 > Duration::compare($this, InputNormalizer::time($other));
    }

    public function isBeforeOrEqual(Time|Event|DateTimeInterface $other): bool
    {
        return 0 >= Duration::compare($this, InputNormalizer::time($other));
    }

    public function equals(Time|Event|DateTimeInterface $other): bool
    {
        return 0 === Duration::compare($this, InputNormalizer::time($other));
    }

    public function isAfterOrEqual(Time|Event|DateTimeInterface $other): bool
    {
        return 0 <= Duration::compare($this, InputNormalizer::time($other));
    }

    public function isAfter(Time|Event|DateTimeInterface $other): bool
    {
        return 0 < Duration::compare($this, InputNormalizer::time($other));
    }

    /**
     * Checks if this instance is within a certain bound.
     *
     * If the value is in range it returns the value, if the value is not in range it returns the nearest bound.
     *
     * @throws InvalidTime
     */
    public function clamp(Time|Event|DateTimeInterface $min, Time|Event|DateTimeInterface $max): self
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
     * @throws TokeiException
     */
    public function add(Duration|DateInterval|Interval|Task ...$duration): self
    {
        $offset = Duration::zero()->add(...array_map(InputNormalizer::duration(...), $duration));

        return $offset->isZero() ? $this : self::sinceMidnight($this->offset()->add($offset));
    }

    /**
     * Alter the time by subtracting a duration object.
     *
     * The duration will be added or subtract depending on its sign.
     *
     * @throws TokeiException
     */
    public function sub(Duration|DateInterval|Interval|Task ...$duration): self
    {
        $offset = Duration::zero()->add(...array_map(InputNormalizer::duration(...), $duration));

        return $offset->isZero() ? $this : self::sinceMidnight($this->offset()->sub($offset));
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
     * @throws TokeiException
     */
    public function diff(Time|Event|DateTimeInterface $other): Duration
    {
        return InputNormalizer::time($other)->offset()->sub($this->offset());
    }

    /**
     * Returns the forward cyclic difference (24 wrap) between this instance and a specified time.
     *
     * @throws TokeiException
     */
    public function distance(Time|Event|DateTimeInterface $other): Duration
    {
        return $this->diff($other)->modulo(Duration::fullDay());
    }
}
