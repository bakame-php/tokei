<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

use function abs;
use function array_column;
use function array_reduce;
use function array_shift;
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
        $microseconds = abs($this->value);
        $this->weeksCount = Unit::Week->whole($microseconds);
        $this->daysCount = Unit::Day->whole($microseconds);
        $this->hours = Unit::Hour->whole($microseconds);
        $microseconds = Unit::Hour->remainder($microseconds);
        $this->minutes = Unit::Minute->whole($microseconds);
        $microseconds = Unit::Minute->remainder($microseconds);
        $this->seconds = Unit::Second->whole($microseconds);
        $this->microseconds = Unit::Second->remainder($microseconds);
    }

    /**
     * @throws InvalidDuration if the value can not be inverted
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
        0 <= $weeks || throw new InvalidDuration('the weeks can not be a expressed with a negative number.');
        0 <= $days || throw new InvalidDuration('the days can not be a expressed with a negative number.');
        0 <= $hours || throw new InvalidDuration('the hours can not be a expressed with a negative number.');
        0 <= $minutes || throw new InvalidDuration('the minutes can not be a expressed with a negative number.');
        0 <= $seconds || throw new InvalidDuration('the seconds can not be a expressed with a negative number.');
        0 <= $milliseconds || throw new InvalidDuration('the milliseconds can not be a expressed with a negative number.');
        0 <= $microseconds || throw new InvalidDuration('the microseconds can not be a expressed with a negative number.');

        return new self(self::toMicroseconds(
            days: ($weeks * 7) + $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: Unit::Millisecond->toMicroseconds($milliseconds) + $microseconds
        ));
    }

    private static function toMicroseconds(
        int $days,
        int $hours,
        int $minutes,
        int|float $seconds,
        int $microseconds
    ): int {
        return Unit::Day->toMicroseconds($days)
            + Unit::Hour->toMicroseconds($hours)
            + Unit::Minute->toMicroseconds($minutes)
            + Unit::Second->toMicroseconds($seconds)
            + $microseconds;
    }

    /**
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
            microseconds: Unit::Second->toMicroseconds($interval->f),
        );

        return new self(1 === $interval->invert ? -$microseconds : $microseconds);
    }

    /**
     * @throws InvalidDuration
     */
    public static function fromNotation(string $value, DurationNotation $notation): self
    {
        return $notation->decode($value);
    }

    /**
     * @throws InvalidDuration
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * @throws InvalidDuration
     */
    public static function max(): self
    {
        return new self(PHP_INT_MAX - 1);
    }

    /**
     * @throws InvalidDuration
     */
    public static function min(): self
    {
        return new self(PHP_INT_MIN + 2);
    }

    /**
     * @throws InvalidDuration
     */
    public static function minOf(self ...$durations): self
    {
        [] !== $durations || throw new InvalidDuration('minOf() expects at least one duration');
        $min = array_shift($durations);

        return array_reduce($durations, fn (self $min, self $item): self => $item->isShorterThan($min) ? $item : $min, $min);
    }

    /**
     * @throws InvalidDuration
     */
    public static function maxOf(self ...$durations): self
    {
        [] !== $durations || throw new InvalidDuration('maxOf() expects at least one duration');
        $max = array_shift($durations);

        return array_reduce($durations, fn (self $max, self $item): self => $item->isLongerThan($max) ? $item : $max, $max);
    }

    /**
     * @return non-empty-string
     */
    public function toNotation(DurationNotation $notation = DurationNotation::Iso8601): string
    {
        return $notation->encode($this);
    }

    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        $interval->d = $this->daysCount;
        $interval->h = $this->hours % 24;
        $interval->i = $this->minutes;
        $interval->s = $this->seconds;
        if (0 !== $this->microseconds) {
            $interval->f = Unit::Second->divide($this->microseconds);
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

    public function total(Unit $unit): int|float
    {
        return $unit->divide($this->value);
    }

    /**
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->toNotation();
    }

    /**
     * Returns true when the duration is zero, false otherwise.
     */
    public function isEmpty(): bool
    {
        return 0 === $this->value;
    }

    /**
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
     * @throws InvalidDuration
     */
    public function roundTo(Unit $precision, RoundingMode $roundingMode = RoundingMode::Nearest): self
    {
        $micro = abs($this->value);
        $rounded = $precision->round($micro, $roundingMode);

        return $micro === $rounded ? $this : new self($this->sign * $rounded);
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
     * @throws InvalidDuration if the value can not be inverted
     */
    public function increment(
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
     * @throws InvalidDuration if the value can not be inverted
     */
    public function decrement(
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
     * @return int<-1, 1>
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

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
     * @param array{0: array{microseconds: int}, 1:array{}} $data
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
