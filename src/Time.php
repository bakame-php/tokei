<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArgumentCountError;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use Time\Duration as TimeDuration;

use function array_key_first;
use function array_key_last;
use function array_map;
use function intdiv;
use function preg_match;
use function str_pad;
use function trim;
use function usort;

final class Time implements JsonSerializable
{
    private const string REGEXP_ISO8601 = '@^
        (?<hour>\d{1,2}):
        (?<minute>\d{1,2})
        (:(?<second>\d{1,2}))?
        (?:\.(?<fractions>\d{1,9}))?
    $@x';

    private const string REGEXP_COMPACT = '@^
        (?<hour>\d{1,2})\s*h\s*
        (?<minute>\d{1,2})\s*m\s*
        (?:(?<second>\d{1,2})\s*s\s*)?
        (?:
            (?<fractions>\d{1,9})\s*
            (?<unit>µs|us|ms|ns)\s*
        )?
    $@x';

    private ?DurationParts $parts = null;
    private readonly Duration $offset;
    public int $hour { get => $this->parts()->hour; }
    public int $minute { get => $this->parts()->minute; }
    public int $second { get => $this->parts()->second; }
    public int $nanosecond { get => $this->parts()->nanoseconds; }

    /**
     * @throws TokeiException
     */
    private function __construct(Duration $duration)
    {
        $this->offset = $duration->modulo(Duration::fullDay());
    }

    /**
     * @return array{0: array{offset: Duration}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['offset' => $this->offset], []];
    }

    /**
     * @param array{0: array{offset: Duration}, 1:array{}} $data
     *
     * @throws TokeiException
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->offset = $properties['offset']->modulo(Duration::fullDay());
    }

    private function parts(): DurationParts
    {
        return $this->parts ??= new DurationParts($this->offset);
    }

    /**
     * @throws TokeiException
     */
    public static function at(
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        int $nanosecond = 0,
    ): self {
        ($hour >= 0 && $hour < 24) || throw InvalidTime::dueToMalformedTime($hour, Unit::Hour);
        ($minute >= 0 && $minute < 60) || throw InvalidTime::dueToMalformedTime($minute, Unit::Minute);
        ($second >= 0 && $second < 60) || throw InvalidTime::dueToMalformedTime($second, Unit::Second);
        ($nanosecond >= 0 && $nanosecond < 1_000_000_000) || throw InvalidTime::dueToMalformedTime($nanosecond, Unit::Nanosecond);

        return self::sinceMidnight(Duration::of(
            hours: $hour,
            minutes: $minute,
            seconds: $second,
            nanoseconds: $nanosecond,
        ));
    }

    /**
     * Returns a new instance from a DateTimeInterface object.
     *
     * @throws TokeiException
     */
    public static function fromDateTime(DateTimeInterface $datetime): self
    {
        return self::at(
            (int) $datetime->format('H'),
            (int) $datetime->format('i'),
            (int) $datetime->format('s'),
            ((int) $datetime->format('u')) * 1_000,
        );
    }

    /**
     * @throws TokeiException
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
            $parts['fractions'] = str_pad($parts['fractions'] ?? '0', 9, '0');
        }

        return Time::at(
            hour: (int) $parts['hour'],
            minute: (int) $parts['minute'],
            second: (int) ($parts['second'] ?? 0),
            nanosecond: self::resolveFractions($parts),
        );
    }

    /**
     * @param array{fractions?: string, unit?: ?non-empty-string} $parts
     *
     * @throws InvalidTime
     */
    private static function resolveFractions(array $parts): int
    {
        $value = (int)($parts['fractions'] ?? 0);
        $value >= 0 || throw new InvalidTime('fraction value cannot be negative.');

        $unit = $parts['unit'] ?? 'ns';
        if ('ms' === $unit) {
            $value <= 999 || throw new InvalidTime('millisecond fraction value cannot be greater than 999.');

            return $value * 1_000_000;
        }

        if ('µs' === $unit || 'us' === $unit) {
            $value <= 999_999 || throw new InvalidTime('microsecond fraction value cannot be greater than 999_999.');

            return $value * 1_000;
        }

        $value <= 999_999_999 || throw new InvalidTime('nanosecond fraction value cannot be greater than 999_999_999.');

        return $value;
    }

    /**
     * Returns a new instance from a number of unit of time since midnight.
     */
    public static function sinceMidnight(Duration|DateInterval|Interval|Task|TimeDuration $duration): self
    {
        return new self(InputNormalizer::duration($duration));
    }

    public static function midnight(): self
    {
        return self::sinceMidnight(Duration::zero());
    }

    public static function noon(): self
    {
        return self::sinceMidnight(Duration::of(hours: 12));
    }

    public static function endOfDay(): self
    {
        return self::sinceMidnight(Duration::of(nanoseconds: 1)->negate());
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
     */
    public function offset(): Duration
    {
        return $this->offset;
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
    public function add(Duration|DateInterval|Interval|Task|TimeDuration ...$duration): self
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
    public function sub(Duration|DateInterval|Interval|Task|TimeDuration ...$duration): self
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
        ?int $nanosecond = null
    ): self {
        $hour ??= $this->hour;
        $minute ??= $this->minute;
        $second ??= $this->second;
        $nanosecond ??= $this->nanosecond;

        return $hour === $this->hour
            && $minute === $this->minute
            && $second === $this->second
            && $nanosecond === $this->nanosecond
            ? $this : self::at($hour, $minute, $second, $nanosecond);
    }

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $offset = $this->offset();
        $duration = $offset->roundTo($unit, $mode);

        return $duration->equals($offset) ? $this : self::sinceMidnight($duration);
    }

    /**
     * Returns a new DateTimeImmutable instance on which the current time is applied.
     */
    public function applyTo(DateTimeInterface $datetime): DateTimeImmutable
    {
        if (!$datetime instanceof DateTimeImmutable) {
            $datetime = DateTimeImmutable::createFromInterface($datetime);
        }

        return $datetime->setTime($this->hour, $this->minute, $this->second, intdiv($this->nanosecond, 1_000));
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
