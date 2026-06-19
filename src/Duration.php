<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use ValueError;

use function array_column;
use function array_sum;
use function intdiv;
use function is_int;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final readonly class Duration implements JsonSerializable
{
    public int $hours;
    public int $minutes;
    public int $seconds;
    public int $microseconds;
    public int $sign;
    public int $daysCount;
    public int $weeksCount;

    /**
     * @param int $value expressed in microseconds
     *
     * @throws InvalidDuration
     */
    private function __construct(private int $value)
    {
        ($value > PHP_INT_MIN + 1 && $value < PHP_INT_MAX) || throw InvalidDuration::dueToOverflow();

        $parts = UnitTransformer::decompose($this->value);

        $this->weeksCount = $parts->weeksCount;
        $this->daysCount = $parts->daysCount;
        $this->hours = $parts->hours;
        $this->minutes = $parts->minutes;
        $this->seconds = $parts->seconds;
        $this->microseconds = $parts->microseconds;
        $this->sign = $parts->sign;
    }

    /**
     * @param non-negative-int $weeks
     * @param non-negative-int $days
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $milliseconds
     * @param non-negative-int $microseconds
     *
     * @throws InvalidDuration
     */
    public static function of(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0,
    ): self {
        /* @phpstan-ignore-next-line */
        (0 <= $weeks && 0 <= $days && 0 <= $hours && 0 <= $minutes && 0 <= $seconds && 0 <= $milliseconds && 0 <= $microseconds) || throw new InvalidDuration('No duration part can be expressed with a negative number.');

        return new self(UnitTransformer::compose(
            days: ($weeks * 7) + $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: UnitTransformer::toMicroseconds($milliseconds, Unit::Millisecond) + $microseconds,
            sign: 1
        ));
    }

    /**
     * Returns a new instance from a DateInterval object.
     *
     * if the DateInterval days property is false
     * and one of the y or m properties is set
     * an exception will be thrown as the object
     * will contain non-deterministic values
     *
     * @throws InvalidDuration
     */
    public static function fromDateInterval(DateInterval $interval): self
    {
        false !== $interval->days || (0 === $interval->y && 0 === $interval->m) || throw new InvalidDuration('fromDateInterval() does not handle non deterministic DateInterval properties like months and years.');
        (0.0 <= $interval->f && 1.0 > $interval->f) || throw new InvalidDuration('Invalid fractional seconds in DateInterval.');

        return new self(UnitTransformer::compose(
            days: false === $interval->days ? $interval->d : $interval->days,
            hours: $interval->h,
            minutes: $interval->i,
            seconds: $interval->s,
            microseconds: UnitTransformer::toMicroseconds($interval->f, Unit::Second),
            sign: 1 === $interval->invert ? -1 : 1
        ));
    }

    /**
     * @see DurationFormat::decode()
     *
     * @throws InvalidDuration
     */
    public static function fromFormat(string $value, DurationFormat $format = DurationFormat::Iso8601): self
    {
        return $format->decode($value);
    }

    /**
     * Returns an instance with 0s duration.
     */
    public static function zero(): self
    {
        /** @var ?self $duration */
        static $duration = null;

        return $duration ??= new self(0);
    }

    /**
     * Returns an instance with the highest duration value supported by the package.
     */
    public static function max(): self
    {
        /** @var ?self $duration */
        static $duration = null;

        return $duration ??= new self(PHP_INT_MAX - 1);
    }

    /**
     * Returns an instance with the lowest duration value supported by the package.
     */
    public static function min(): self
    {
        /** @var ?self $duration */
        static $duration = null;

        return $duration ??= new self(PHP_INT_MIN + 2);
    }

    /**
     * Returns the shortest instance from a collection of instances.
     *
     * @throws ValueError if no argument is given
     */
    public static function minOf(self ...$durations): self
    {
        $value = null;
        foreach ($durations as $duration) {
            if (null === $value || $duration->isShorterThan($value)) {
                $value = $duration;
            }
        }

        return null !== $value ? $value : throw new ValueError('minOf() expects at least one duration');
    }

    /**
     * Returns the longest instance from a collection of instances.
     *
     * @throws ValueError if no argument is given
     */
    public static function maxOf(self ...$durations): self
    {
        $value = null;
        foreach ($durations as $duration) {
            if (null === $value || $duration->isLongerThan($value)) {
                $value = $duration;
            }
        }

        return null !== $value ? $value : throw new ValueError('maxOf() expects at least one duration');
    }

    /**
     * @see DurationFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(DurationFormat $format = DurationFormat::Iso8601): string
    {
        return $format->encode($this);
    }

    /**
     * Converts the instance to an DateInterval object.
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        $interval->d = $this->daysCount;
        $interval->h = $this->hours % 24;
        $interval->i = $this->minutes;
        $interval->s = $this->seconds;
        if (0 !== $this->microseconds) {
            $interval->f = UnitTransformer::fromMicroseconds($this->microseconds, Unit::Second);
        }
        $interval->invert = -1 === $this->sign ? 1 : 0;
        if (null === $relativeTo) {
            return $interval;
        }

        if (!$relativeTo instanceof DateTimeImmutable) {
            $relativeTo = DateTimeImmutable::createFromInterface($relativeTo);
        }

        return $relativeTo->diff($relativeTo->add($interval));
    }

    /**
     * Returns the Duration as expressed in the specified Unit of time.
     */
    public function total(Unit $unit): int|float
    {
        return UnitTransformer::fromMicroseconds($this->value, $unit);
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
     * Returns true when the duration is zero, false otherwise.
     */
    public function isZero(): bool
    {
        return 0 === $this->value;
    }

    /**
     * Invert the duration sign.
     *
     * @throws InvalidDuration
     */
    public function negated(): self
    {
        return new self(-$this->value);
    }

    /**
     * @throws InvalidDuration
     */
    public function abs(): self
    {
        return $this->value < 0 ? $this->negated() : $this;
    }

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $rounded = UnitTransformer::round($this->value, $unit, $mode);

        return $this->value === $rounded ? $this : new self($rounded);
    }

    /**
     * @throws InvalidDuration
     */
    public function sum(self ...$other): self
    {
        $other[] = $this;
        $value = array_sum(array_column($other, 'value'));
        is_int($value) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return $this->value === $value ? $this : new self($value);
    }

    /**
     * @param non-negative-int $weeks
     * @param non-negative-int $days
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $milliseconds
     * @param non-negative-int $microseconds
     *
     * @throws InvalidDuration
     */
    public function increase(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0
    ): self {
        return $this->sum(self::of(
            weeks: $weeks,
            days: $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            milliseconds: $milliseconds,
            microseconds: $microseconds
        ));
    }

    /**
     * @param non-negative-int $weeks
     * @param non-negative-int $days
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $milliseconds
     * @param non-negative-int $microseconds
     *
     * @throws InvalidDuration
     */
    public function decrease(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0
    ): self {
        return $this->sum(self::of(
            weeks: $weeks,
            days: $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            milliseconds: $milliseconds,
            microseconds: $microseconds
        )->negated());
    }

    private static function extractDuration(self|Interval|Task|NativeInterval|NativeTask $that): self
    {
        return match (true) {
            $that instanceof NativeInterval => Interval::fromNative($that)->duration,
            $that instanceof NativeTask => Task::fromNative($that)->interval->duration,
            $that instanceof Task => $that->interval->duration,
            $that instanceof Interval => $that->duration,
            $that instanceof self => $that,
        };
    }

    /**
     *  Compare this instance with another.
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public static function compare(
        self|Interval|Task|NativeInterval|NativeTask $that,
        self|Interval|Task|NativeInterval|NativeTask $other
    ): int {
        return self::extractDuration($that)->value <=> self::extractDuration($other)->value;
    }

    /**
     * Tells whether this instance is equal to the specified duration.
     */
    public function equals(self $other): bool
    {
        return 0 === self::compare($this, $other);
    }

    public function isLongerThan(self $other): bool
    {
        return 0 < self::compare($this, $other);
    }

    public function isLongerThanOrEqual(self $other): bool
    {
        return 0 <= self::compare($this, $other);
    }
    public function isShorterThan(self $other): bool
    {
        return 0 > self::compare($this, $other);
    }

    public function isShorterThanOrEqual(self $other): bool
    {
        return 0 >= self::compare($this, $other);
    }

    /**
     * Checks if this instance is within a certain bound.
     *
     * If the value is in range it returns the value, if the value is not in range it returns the nearest bound.
     *
     * @throws InvalidDuration
     */
    public function clamp(self $min, self $max): self
    {
        $max->isLongerThanOrEqual($min) || throw new InvalidDuration('The maximum duration must be longer or equal to the minimum duration.');

        return match (true) {
            $this->isShorterThan($min) => $min,
            $this->isLongerThan($max) => $max,
            default => $this,
        };
    }

    /**
     * @param non-negative-int $factor
     *
     * @throws InvalidDuration if value overflow
     */
    public function multipliedBy(int $factor): self
    {
        0 <= $factor || throw new InvalidDuration('factor must be a non negative integer.');  /* @phpstan-ignore-line */

        $result = $this->value * $factor;

        is_int($result) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return new self($result);
    }

    /**
     * Divides the duration by a factor using truncating integer division.
     *
     * The result is rounded toward zero.
     *
     * @param positive-int $factor
     *
     * @throws InvalidDuration if the factor is zero
     */
    public function dividedBy(int $factor): self
    {
        0 < $factor || throw new InvalidDuration('factor must be a positive integer.');  /* @phpstan-ignore-line */

        return new self(intdiv($this->value, $factor));
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        /** @var int $value */
        $value = $this->total(Unit::Microsecond);

        return [['microseconds' => $value], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1: array{}} $data
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $time = new self($properties['microseconds']);
        $this->value = $time->value;
        $this->hours = $time->hours;
        $this->minutes = $time->minutes;
        $this->seconds = $time->seconds;
        $this->microseconds = $time->microseconds;
        $this->daysCount = $time->daysCount;
        $this->weeksCount = $time->weeksCount;
        $this->sign = $time->sign;
    }
}
