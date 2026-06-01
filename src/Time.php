<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use JsonSerializable;
use Throwable;

use function array_reduce;
use function array_shift;
use function class_exists;
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
        $this->value = Unit::Day->wrap($value);
        $microseconds = 0 > $this->value ? -$this->value : $this->value;
        $this->hour = Unit::Hour->whole($microseconds);
        $microseconds = Unit::Hour->remainder($microseconds);
        $this->minute = Unit::Minute->whole($microseconds);
        $microseconds = Unit::Minute->remainder($microseconds);
        $this->second = Unit::Second->whole($microseconds);
        $this->microsecond = Unit::Second->remainder($microseconds);
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
            Unit::Hour->toMicroseconds($hour)
            + Unit::Minute->toMicroseconds($minute)
            + Unit::Second->toMicroseconds($second)
            + $microsecond
        );
    }

    private static function filterTimezone(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        try {
            return match (true) {
                null === $timezone => null,
                $timezone instanceof DateTimeZone => $timezone,
                default => new DateTimeZone($timezone),
            };
        } catch (Throwable $exception) {
            throw new TimeException('Timezone must be a valid IANA Timezone Name supported by '.DateTimeZone::class, previous: $exception);
        }
    }

    /**
     * @throws InvalidTime
     */
    public static function fromDate(DateTimeInterface $datetime): self
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
    public static function fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601): self
    {
        return $format->decode($value);
    }

    public static function fromOffset(int|float $value, Unit $unit): self
    {
        return new self($unit->toMicroseconds($value));
    }

    public static function midnight(): self
    {
        return new self(0);
    }

    public static function noon(): self
    {
        return new self(Unit::Hour->toMicroseconds(12));
    }

    public static function endOfDay(): self
    {
        return new self(-1);
    }

    /**
     * @throws InvalidTime|TimeException
     */
    public static function now(DateTimeZone|string|null $timezone = null): self
    {
        return self::fromDate(new DateTimeImmutable(timezone: self::filterTimezone($timezone)));
    }

    /**
     * @throws InvalidTime
     */
    public static function minOf(self ...$times): self
    {
        [] !== $times || throw new InvalidTime('minOf() expects at least one time');

        $min = array_shift($times);

        return array_reduce($times, fn (self $min, self $item): self => $item->isBefore($min) ? $item : $min, $min);
    }

    /**
     * @throws InvalidTime
     */
    public static function maxOf(self ...$times): self
    {
        [] !== $times || throw new InvalidTime('maxOf() expects at least one time');
        $max = array_shift($times);

        return array_reduce($times, fn (self $max, self $item): self => $item->isAfter($max) ? $item : $max, $max);
    }

    public function toOffset(Unit $unit): int|float
    {
        return $unit->divide($this->value);
    }

    /**
     * @return non-empty-string
     */
    public function format(TimeFormat $format = TimeFormat::Iso8601): string
    {
        return $format->encode($this);
    }

    /**
     * @throws TimeException
     */
    public function toLocaleString(
        string $locale,
        DateTimeZone|string|null $timezone = null,
        TimeFormatLength $length = TimeFormatLength::Medium
    ): string {
        static $isSupported = null;
        $isSupported = $isSupported ?? class_exists(IntlDateFormatter::class);
        $isSupported || throw new TimeException('Support for time locale formatting requires the `intl` extension for best performance or run "composer require symfony/polyfill-intl-icu" to install a polyfill.');

        $timeType = match ($length) {
            TimeFormatLength::Full => IntlDateFormatter::FULL,
            TimeFormatLength::Long => IntlDateFormatter::LONG,
            TimeFormatLength::Medium => IntlDateFormatter::MEDIUM,
            TimeFormatLength::Short => IntlDateFormatter::SHORT,
        };

        $timezone = self::filterTimezone($timezone);

        try {
            $formatted = (new IntlDateFormatter(
                locale: $locale,
                dateType: IntlDateFormatter::NONE,
                timeType: $timeType,
                timezone: $timezone,
            ))->format($this->applyTo(new DateTimeImmutable(timezone: $timezone)));
        } catch (Throwable $exception) {
            throw new TimeException('Unable to convert to locale "'.$locale.'" the current time; Please verify your locale.', previous: $exception);
        }

        return false !== $formatted ? $formatted : throw new TimeException('Unable to convert to locale "'.$locale.'" the current time.');
    }

    /**
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * @return int<-1, 1>
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function isBefore(self $other): bool
    {
        return 0 > $this->compareTo($other);
    }

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

    public function roundTo(Unit $unit, RoundingMode $roundingMode = RoundingMode::Nearest): self
    {
        $rounded = $unit->round($this->value, $roundingMode);

        return $this->value === $rounded ? $this : new self($rounded);
    }

    public function applyTo(DateTimeInterface $datetime): DateTimeImmutable
    {
        if (!$datetime instanceof DateTimeImmutable) {
            $datetime = DateTimeImmutable::createFromInterface($datetime);
        }

        return $datetime->setTime($this->hour, $this->minute, $this->second, $this->microsecond);
    }

    /**
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
     * @throws InvalidDuration
     */
    public function distance(self $other): Duration
    {
        /** @var non-negative-int $duration */
        $duration = Unit::Day->wrap($other->value - $this->value);

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
