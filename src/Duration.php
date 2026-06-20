<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use ValueError;

use function array_column;
use function array_map;
use function array_sum;
use function intdiv;
use function is_int;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final readonly class Duration implements JsonSerializable
{
    public int $microseconds;
    public int $sign;

    /**
     * @param int $microseconds expressed in microseconds
     *
     * @throws InvalidDuration
     */
    private function __construct(int $microseconds)
    {
        ($microseconds > PHP_INT_MIN + 1 && $microseconds < PHP_INT_MAX) || throw InvalidDuration::dueToOverflow();

        $this->microseconds = $microseconds;
        $this->sign = $this->microseconds <=> 0;
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
     *  Compare this instance with another.
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public static function compare(
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $that,
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other
    ): int {
        return InputNormalizer::duration($that)->microseconds <=> InputNormalizer::duration($other)->microseconds;
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
        $parsed = UnitTransformer::decompose($this->microseconds);
        $interval = new DateInterval('PT0S');
        $interval->d = $parsed->daysCount;
        $interval->h = $parsed->hours % 24;
        $interval->i = $parsed->minutes;
        $interval->s = $parsed->seconds;
        if (0 !== $parsed->microseconds) {
            $interval->f = UnitTransformer::fromMicroseconds($parsed->microseconds, Unit::Second);
        }
        $interval->invert = -1 === $parsed->sign ? 1 : 0;
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
    public function in(Unit $unit): int|float
    {
        return UnitTransformer::fromMicroseconds($this->microseconds, $unit);
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
        return 0 === $this->microseconds;
    }

    /**
     * Invert the duration sign.
     *
     * @throws InvalidDuration
     */
    public function negated(): self
    {
        return new self(-$this->microseconds);
    }

    /**
     * @throws InvalidDuration
     */
    public function abs(): self
    {
        return $this->microseconds < 0 ? $this->negated() : $this;
    }

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $rounded = UnitTransformer::round($this->microseconds, $unit, $mode);

        return $this->microseconds === $rounded ? $this : new self($rounded);
    }

    /**
     * @throws InvalidDuration
     */
    public function sum(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask ...$other): self
    {
        $other = array_map(InputNormalizer::duration(...), $other);
        $other[] = $this;
        $microseconds = array_sum(array_column($other, 'microseconds'));
        is_int($microseconds) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return $this->microseconds === $microseconds ? $this : new self($microseconds);
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
     * Tells whether this instance is equal to the specified duration.
     */
    public function equals(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 === self::compare($this, $other);
    }

    public function isLongerThan(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 < self::compare($this, $other);
    }

    public function isLongerThanOrEqual(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 <= self::compare($this, $other);
    }
    public function isShorterThan(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 > self::compare($this, $other);
    }

    public function isShorterThanOrEqual(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
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
    public function clamp(
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $min,
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $max
    ): self {
        $max = InputNormalizer::duration($max);
        $min = InputNormalizer::duration($min);

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

        $result = $this->microseconds * $factor;

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

        return new self(intdiv($this->microseconds, $factor));
    }

    public function countOf(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): int
    {
        $other = InputNormalizer::duration($other);

        return !$other->isZero()
            ? intdiv($this->microseconds, $other->microseconds)
            : throw new InvalidDuration('Cannot divide by zero duration.');
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['microseconds' => $this->microseconds], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1: array{}} $data
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $duration = new self($properties['microseconds']);
        $this->microseconds = $duration->microseconds;
        $this->sign = $duration->sign;
    }
}
