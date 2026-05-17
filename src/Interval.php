<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Hoa\Stream\Test\Unit\IStream\In;

use function max;
use function min;

/**
 * Represents a start-inclusive, end-exclusive interval between two times on a 24-hour circular clock.
 */
final readonly class Interval
{
    private const int MICRO_PER_DAY = 24 * 60 * 60 * 1_000_000;

    /** @var int the linearized start expressed in microseconds */
    private int $linearStart;
    /** @var int the linearized end expressed in microseconds */
    private int $linearEnd;

    private function __construct(
        public Time $start,
        public Time $end,
        public Duration $duration,
    ) {
        $this->linearStart = $this->start->toMicroOfDay();
        $linearEnd = $this->end->toMicroOfDay();
        if ($linearEnd < $this->linearStart) {
            $linearEnd += Duration::fromIso8601('P1D')->toMicro();
        }

        $this->linearEnd = $linearEnd;
    }

    public static function between(Time $start, Time $end): self
    {
        return new self($start, $end, $start->distance($end));
    }

    /**
     * @throws InvalidDuration
     */
    public static function since(Time $start, Duration $duration): self
    {
        self::assertNonNegativeDuration($duration);

        return self::between($start, $start->add($duration));
    }

    private static function assertNonNegativeDuration(Duration $duration): void
    {
        !$duration->inverted || throw new InvalidDuration('The duration can not be negative.');
    }

    /**
     * @throws InvalidDuration
     */
    public static function until(Time $end, Duration $duration): self
    {
        self::assertNonNegativeDuration($duration);

        return self::between($end->add($duration->negate()), $end);
    }

    /**
     * @throws InvalidDuration
     */
    public static function around(Time $midRange, Duration $duration): self
    {
        self::assertNonNegativeDuration($duration);

        $halfDuration = $duration->dividedBy(2);

        return self::between($midRange->add($halfDuration->negate()), $midRange->add($halfDuration));
    }

    public static function morning(): self
    {
        return self::between(Time::at(hour: 6), Time::noon());
    }

    public static function afternoon(): self
    {
        return self::between(Time::noon(), Time::at(hour: 18));
    }

    public static function evening(): self
    {
        return self::between(Time::at(hour: 18), Time::at(hour: 22));
    }

    public static function night(): self
    {
        return self::between(Time::at(hour: 22), Time::at(hour: 6));
    }

    public static function day(): self
    {
        return self::between(Time::at(hour: 6), Time::at(hour: 22));
    }

    public static function fullDay(): self
    {
        return self::circular(Time::midnight());
    }

    public static function collapsed(Time $at): self
    {
        return new self($at, $at, Duration::zero());
    }

    public static function circular(Time $at): self
    {
        return new self($at, $at, Duration::of(hours: 24));
    }

    public function format(
        string $separator = ':',
        PaddingMode $padding = PaddingMode::Padded,
        SubSecondDisplay $subSecondDisplay = SubSecondDisplay::Auto,
    ): string {
        return '['.$this->start->format($separator, $padding, $subSecondDisplay).','.$this->end->format($separator, $padding, $subSecondDisplay).')';
    }

    public function isCircular(): bool
    {
        return self::MICRO_PER_DAY === $this->duration->toMicro();
    }

    public function isCollapsed(): bool
    {
        return 0 === $this->duration->toMicro();
    }

    public function startingOn(Time $time): self
    {
        return $time->equals($this->start) ? $this : self::between($time, $this->end);
    }

    public function endingOn(Time $time): self
    {
        return $time->equals($this->end) ? $this : self::between($this->start, $time);
    }

    public function shift(Duration $duration): self
    {
        return $duration->isEmpty() ? $this : self::between($this->start->add($duration), $this->end->add($duration));
    }

    public function shiftStart(Duration $duration): self
    {
        return $duration->isEmpty() ? $this : self::between($this->start->add($duration), $this->end);
    }

    public function shiftEnd(Duration $duration): self
    {
        return $duration->isEmpty() ? $this : self::between($this->start, $this->end->add($duration));
    }

    /**
     * @throws InvalidDuration
     */
    public function lastingFromStart(Duration $duration): self
    {
        self::assertNonNegativeDuration($duration);

        return self::between($this->start, $this->start->add($duration));
    }

    /**
     * @throws InvalidDuration
     */
    public function lastingFromEnd(Duration $duration): self
    {
        self::assertNonNegativeDuration($duration);

        return self::between($this->end->add($duration->negate()), $this->end);
    }

    public function expand(Duration $duration): self
    {
        return self::between($this->start->add($duration->negate()), $this->end->add($duration));
    }

    public function complement(): self
    {
        return match (true) {
            $this->isCollapsed() => self::circular($this->start),
            $this->isCircular() => self::collapsed($this->start),
            default => self::between($this->end, $this->start),
        };
    }

    /**
     * @throws InvalidDuration
     *
     * @return iterable<Time>
     */
    public function rangeForward(Duration $step): iterable
    {
        self::assertNonNegativeDuration($step);
        if ($this->isCollapsed()) {
            return;
        }

        $step = $step->toMicro();
        $cursor = $this->linearStart;
        $end = $this->linearEnd;

        while ($cursor <= $end) {
            yield Time::atMicroOfDay($cursor);

            $next = $cursor + $step;
            if ($next === $cursor) {
                break;
            }

            $cursor = $next;
        }
    }

    /**
     * @throws InvalidDuration
     *
     * @return iterable<Time>
     */
    public function rangeBackward(Duration $step): iterable
    {
        self::assertNonNegativeDuration($step);

        if ($this->isCollapsed()) {
            return;
        }

        $step = $step->toMicro();

        $cursor = $this->linearEnd;
        $start = $this->linearStart;

        while ($cursor >= $start) {
            yield Time::atMicroOfDay($cursor);
            $next = $cursor - $step;
            if ($next === $cursor) {
                break;
            }

            $cursor = $next;
        }
    }

    /**
     * @throws InvalidDuration
     *
     * @return iterable<Interval>
     */
    public function splitForward(Duration $step): iterable
    {
        self::assertNonNegativeDuration($step);
        if ($this->isCollapsed()) {
            return;
        }

        $stepMicro = $step->toMicro();
        $start = $this->linearStart;
        $end = $this->linearEnd;

        while ($start < $end) {
            $next = $start + $stepMicro;
            if ($next > $end) {
                $next = $end;
            }

            if ($next === $start) {
                break;
            }

            yield self::between(Time::atMicroOfDay($start), Time::atMicroOfDay($next));
            $start = $next;
        }
    }

    /**
     * @throws InvalidDuration
     *
     * @return iterable<Interval>
     */
    public function splitBackward(Duration $step): iterable
    {
        self::assertNonNegativeDuration($step);
        if ($this->isCollapsed()) {
            return;
        }

        $stepMicro = $step->toMicro();
        $start = $this->linearStart;
        $end = $this->linearEnd;

        while ($end > $start) {
            $prev = $end - $stepMicro;
            if ($prev < $start) {
                $prev = $start;
            }

            if ($prev === $end) {
                break;
            }

            yield self::between(Time::atMicroOfDay($prev), Time::atMicroOfDay($end));

            $end = $prev;
        }
    }

    public function splitAt(Time $time): IntervalSet
    {
        if ($this->isCollapsed()) {
            return new IntervalSet();
        }

        if ($this->includes($time)) {
            return new IntervalSet(
                Interval::between($this->start, $time),
                Interval::between($time, $this->end),
            );
        }

        if ($this->start->equals($time) || $this->end->equals($time)) {
            return new IntervalSet($this);
        }

        return new IntervalSet();
    }

    public function compareDurationTo(self $other): int
    {
        return $this->duration->compareTo($other->duration);
    }

    public function sameDurationAs(self $other): bool
    {
        return 0 === $this->compareDurationTo($other);
    }

    public function longerThan(self $other): bool
    {
        return 0 < $this->compareDurationTo($other);
    }

    public function longerThanOrEqual(self $other): bool
    {
        return 0 <= $this->compareDurationTo($other);
    }

    public function shorterThan(self $other): bool
    {
        return 0 > $this->compareDurationTo($other);
    }

    public function shorterThanOrEqual(self $other): bool
    {
        return 0 >= $this->compareDurationTo($other);
    }

    public function equals(self $other): bool
    {
        return $this->start->equals($other->start)
            && $this->end->equals($other->end)
            && $this->duration->equals($other->duration);
    }

    public function includes(Time $time): bool
    {
        if ($this->isCircular()) {
            return true;
        }

        if ($this->isCollapsed()) {
            return false;
        }

        $timeInMicro = $time->toMicroOfDay();

        if ($this->end->isAfterOrEqual($this->start)) {
            return $timeInMicro >= $this->linearStart
                && $timeInMicro < $this->linearEnd;
        }

        if ($timeInMicro < $this->linearStart) {
            $timeInMicro += self::MICRO_PER_DAY;
        }

        return $timeInMicro >= $this->linearStart
            && $timeInMicro < $this->linearEnd;
    }

    public function contains(self $other): bool
    {
        return $this->includes($other->start)
            && ($this->includes($other->end) || $this->end->equals($other->end));
    }

    public function overlaps(self $other): bool
    {
        return $this->includes($other->start)
            || $other->includes($this->start);
    }

    public function abuts(self $other): bool
    {
        return $this->end->equals($other->start)
            || $other->end->equals($this->start);
    }

    public function intersect(self $other): ?self
    {
        return !$this->overlaps($other) ? null : self::between(
            Time::atMicroOfDay(max($this->linearStart, $other->linearStart)),
            Time::atMicroOfDay(min($this->linearEnd, $other->linearEnd))
        );
    }

    public function gap(self $other): ?self
    {
        return match (true) {
            $this->overlaps($other) => null,
            $this->linearEnd <= $other->linearStart => self::between(
                Time::atMicroOfDay($this->linearEnd),
                Time::atMicroOfDay($other->linearStart)
            ),
            default => self::between(
                Time::atMicroOfDay($other->linearEnd),
                Time::atMicroOfDay($this->linearStart)
            ),
        };
    }

    public function union(self ...$other): IntervalSet
    {
        return (new IntervalSet($this, ...$other))->union();
    }

    public function difference(self $other): IntervalSet
    {
        return (new IntervalSet($this))->difference($other);
    }
}
