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

        $this->sign = $this->value <=> 0 ;
        $microseconds = 0 > $this->value ? -$this->value : $this->value;
        $this->weeksCount = UnitTransformer::whole($microseconds, Unit::Week);
        $this->daysCount = UnitTransformer::whole($microseconds, Unit::Day);
        $this->hours = UnitTransformer::whole($microseconds, Unit::Hour);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Hour);
        $this->minutes = UnitTransformer::whole($microseconds, Unit::Minute);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Minute);
        $this->seconds = UnitTransformer::whole($microseconds, Unit::Second);
        $this->microseconds = UnitTransformer::remainder($microseconds, Unit::Second);
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

        return new self(self::toMicroseconds(
            days: ($weeks * 7) + $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: UnitTransformer::toMicroseconds($milliseconds, Unit::Millisecond) + $microseconds
        ));
    }

    private static function toMicroseconds(
        int $days,
        int $hours,
        int $minutes,
        int|float $seconds,
        int $microseconds
    ): int {
        return UnitTransformer::toMicroseconds($days, Unit::Day)
            + UnitTransformer::toMicroseconds($hours, Unit::Hour)
            + UnitTransformer::toMicroseconds($minutes, Unit::Minute)
            + UnitTransformer::toMicroseconds($seconds, Unit::Second)
            + $microseconds;
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

        $microseconds = self::toMicroseconds(
            days: false === $interval->days ? $interval->d : $interval->days,
            hours: $interval->h,
            minutes: $interval->i,
            seconds: $interval->s,
            microseconds: UnitTransformer::toMicroseconds($interval->f, Unit::Second),
        );

        return new self(1 === $interval->invert ? -$microseconds : $microseconds);
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

    /**
     *  Compare this instance with another.
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    /**
     * Tells whether this instance is equal to the specified duration.
     */
    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    public function isLongerThan(self $other): bool
    {
        return 0 < $this->compareTo($other);
    }

    public function isLongerThanOrEqual(self $other): bool
    {
        return 0 <= $this->compareTo($other);
    }
    public function isShorterThan(self $other): bool
    {
        return 0 > $this->compareTo($other);
    }

    public function isShorterThanOrEqual(self $other): bool
    {
        return 0 >= $this->compareTo($other);
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
     * @throws InvalidDuration if value overflow
     */
    public function multipliedBy(int $factor): self
    {
        $result = $this->value * $factor;

        is_int($result) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return new self($result);
    }

    /**
     * Divides the duration by a factor using truncating integer division.
     *
     * The result is rounded toward zero.
     *
     * @throws InvalidDuration if the factor is zero
     */
    public function dividedBy(int $factor): self
    {
        0 !== $factor || throw new InvalidDuration('Unable to divide by zero.');

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
