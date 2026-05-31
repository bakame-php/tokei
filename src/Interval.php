<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

use function array_filter;
use function usort;

/**
 * Represents a start-inclusive, end-exclusive interval between two times on a 24-hour circular clock.
 * @phpstan-type NativeInterval array{startDate: DateTimeImmutable, interval: DateInterval}
 */
final readonly class Interval implements JsonSerializable
{
    public Time $start;
    public Time $end;
    public Duration $duration;
    public IntervalType $type;
    /** @var int the linearized start expressed in microseconds */
    public int $linearStart;
    /** @var int the linearized end expressed in microseconds */
    public int $linearEnd;

    private function __construct(Time $start, Duration $duration)
    {
        $this->start = $start;
        $this->duration = $duration;
        $this->linearStart = (int) $this->start->toUnitOfDay(Unit::Microsecond);
        $this->linearEnd = $this->linearStart + (int) $duration->total(Unit::Microsecond);
        $this->end = Time::fromUnitOfDay($this->linearEnd, Unit::Microsecond);
        $this->type = $this->setType();
    }

    private function setType(): IntervalType
    {
        return match ($this->start->compareTo($this->end)) {
            1 => IntervalType::Overflow,
            -1 => IntervalType::Linear,
            0 => 0 === $this->duration->sign ? IntervalType::Collapsed : IntervalType::Circular,
        };
    }

    /**
     * @throws InvalidDuration
     */
    public static function since(Time $start, Duration $duration): self
    {
        return new self($start, $start->distance($start->add($duration)));
    }

    /**
     * @throws InvalidDuration
     */
    public static function until(Time $end, Duration $duration): self
    {
        $start = $end->add($duration->negated());

        return new self($start, $start->distance($end));
    }

    /**
     * @throws InvalidDuration
     */
    public static function around(Time $midRange, Duration $duration): self
    {
        $midDuration = $duration->dividedBy(2);

        return self::between($midRange->add($midDuration->negated()), $midRange->add($midDuration));
    }

    /**
     * @throws InvalidDuration
     */
    public static function between(Time $start, Time $end): self
    {
        return new self($start, $start->distance($end));
    }

    /**
     * @throws InvalidDuration|InvalidTime|InvalidInterval
     */
    public static function fromNotation(string $value, IntervalNotation $notation, ?Unit $unitOfDay = null): self
    {
        return $notation->decode($value, $unitOfDay);
    }

    /**
     * @param int $linearStart the starting time represented on a linear span in microseconds
     * @param int $linearEnd the ending time represented on a linear span in microseconds
     *
     * @throws InvalidDuration
     * @throws InvalidInterval
     */
    public static function fromLinearSpan(int $linearStart, int $linearEnd): self
    {
        $linearStart <= $linearEnd || throw new InvalidInterval('Invalid linear span: the start must be shorter or equal to the end linear span.');

        return new self(Time::fromUnitOfDay($linearStart, Unit::Microsecond), Duration::of(microseconds: $linearEnd - $linearStart));
    }

    /**
     * Returns a Circular interval using midnight as endpoint.
     *
     * @throws InvalidDuration
     */
    public static function fullDay(): self
    {
        return self::circular(Time::midnight());
    }

    /**
     * @throws InvalidDuration
     */
    public static function circular(Time $at): self
    {
        return new self($at, Duration::of(hours: 24));
    }

    /**
     * @throws InvalidDuration
     */
    public static function collapsed(Time $at): self
    {
        return new self($at, Duration::zero());
    }

    /**
     * @see https://en.wikipedia.org/wiki/Interval_(mathematics)#Notations_for_intervals
     * @see https://en.wikipedia.org/wiki/ISO_31-11
     *
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function toNotation(IntervalNotation $notation = IntervalNotation::Iso8601StartDuration, ?Unit $unitOfDay = null): string
    {
        return $notation->encode($this, $unitOfDay);
    }

    /**
     * @return NativeInterval
     */
    public function toNative(DateTimeInterface $reference): array
    {
        return [
            'startDate' => $this->start->applyTo($reference),
            'interval' => $this->duration->toDateInterval(),
        ];
    }

    /**
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->toNotation();
    }

    /**
     * @throws InvalidDuration
     */
    public function startingOn(Time $time): self
    {
        return $time->equals($this->start) ? $this : self::between($time, $this->end);
    }

    /**
     * @throws InvalidDuration
     */
    public function endingOn(Time $time): self
    {
        return $time->equals($this->end) ? $this : self::between($this->start, $time);
    }

    /**
     * @throws InvalidDuration
     */
    public function shift(Duration $duration): self
    {
        return $duration->isEmpty() ? $this : self::between($this->start->add($duration), $this->end->add($duration));
    }

    /**
     * @throws InvalidDuration
     */
    public function shiftBound(Bound $bound, Duration $duration): self
    {
        return match (true) {
            $duration->isEmpty() => $this,
            Bound::Start === $bound =>  self::between($this->start->add($duration), $this->end),
            Bound::End === $bound =>  self::between($this->start, $this->end->add($duration)),
        };
    }

    /**
     * @throws InvalidDuration
     */
    public function lasting(Bound $from, Duration $duration): self
    {
        return match ($from) {
            Bound::Start => self::between($this->start, $this->start->add($duration)),
            Bound::End => self::between($this->end->add($duration->negated()), $this->end),
        };
    }

    /**
     * @throws InvalidDuration
     */
    public function expand(Duration $duration): self
    {
        return self::between($this->start->add($duration->negated()), $this->end->add($duration));
    }

    /**
     * @throws InvalidDuration
     */
    public function roundTo(Unit $unit, RoundingMode $roundingMode): self
    {
        $start = $this->start->roundTo($unit, $roundingMode);
        $end = $this->end->roundTo($unit, $roundingMode);

        return $start->equals($this->start) && $end->equals($this->end) ? $this : self::between($start, $end);
    }

    /**
     * @throws InvalidDuration
     */
    public function complement(): self
    {
        return match ($this->type) {
            IntervalType::Collapsed => self::circular($this->start),
            IntervalType::Circular => self::collapsed($this->start),
            default => self::between($this->end, $this->start),
        };
    }

    /**
     * @throws InvalidDuration
     *
     * @return iterable<Time>
     */
    public function steps(Duration $duration, Bound $from = Bound::Start): iterable
    {
        self::assertPositiveDuration($duration);
        if (IntervalType::Collapsed === $this->type) {
            return;
        }
        /** @var int $step */
        $step = $duration->total(Unit::Microsecond);
        $start = Bound::Start === $from ? $this->linearStart : $this->linearEnd;
        $end = Bound::Start === $from ? $this->linearEnd : $this->linearStart;
        $direction = $start <= $end ? 1 : -1;
        $step *= $direction;
        for ($cursor = $start; $direction > 0 ? $cursor <= $end : $cursor >= $end; $cursor += $step) {
            if ($cursor !== $this->linearEnd) {
                yield Time::fromUnitOfDay($cursor, Unit::Microsecond);
            }
        }
    }

    /**
     * @throws InvalidDuration
     */
    private static function assertPositiveDuration(Duration $duration): void
    {
        1 === $duration->sign || throw new InvalidDuration('The duration can not be negative or equal to 0.');
    }

    /**
     * @throws InvalidDuration
     */
    public function splitBy(Duration $duration, Bound $from = Bound::Start): IntervalSet
    {
        self::assertPositiveDuration($duration);
        if (IntervalType::Collapsed === $this->type) {
            return new IntervalSet();
        }

        $step = $duration->total(Unit::Microsecond);
        $start = $this->linearStart;
        $end = $this->linearEnd;
        $forward = Bound::Start === $from;
        $cursor = $forward ? $start : $end;
        $limit = $forward ? $end : $start;
        $result = [];
        while ($forward ? $cursor < $limit : $cursor > $limit) {
            /** @var int $next */
            $next = $forward ? min($cursor + $step, $limit) : max($cursor - $step, $limit);
            $result[] = $forward
                ? self::between(Time::fromUnitOfDay($cursor, Unit::Microsecond), Time::fromUnitOfDay($next, Unit::Microsecond))
                : self::between(Time::fromUnitOfDay($next, Unit::Microsecond), Time::fromUnitOfDay($cursor, Unit::Microsecond));

            $cursor = $next;
        }

        return new IntervalSet(...$result);
    }

    /**
     * @throws InvalidDuration
     */
    public function splitAt(Time ...$steps): IntervalSet
    {
        $steps = array_filter($steps, fn (Time $time): bool => $time->isAfter($this->start) && $time->isBefore($this->end));
        usort($steps, static fn (Time $a, Time $b): int => $a->compareTo($b));

        $result = [];
        $cursor = $this->start;
        foreach ($steps as $time) {
            $it = self::between($cursor, $time);
            if (IntervalType::Collapsed !== $it->type) {
                $result[] = $it;
            }
            $cursor = $time;
        }

        if (!$cursor->equals($this->end)) {
            $result[] = self::between($cursor, $this->end);
        }

        return new IntervalSet(...$result);
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
            && $this->duration->equals($other->duration);
    }

    public function includes(Time $time): bool
    {
        if (IntervalType::Circular === $this->type) {
            return true;
        }

        if (IntervalType::Collapsed === $this->type) {
            return false;
        }

        $timeInMicro = $time->toUnitOfDay(Unit::Microsecond);
        if ($this->linearEnd > $this->linearStart && $timeInMicro < $this->linearStart) {
            $timeInMicro += Unit::Day->microseconds();
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

    /**
     * @throws InvalidInterval|InvalidDuration
     */
    public function intersect(self $other): ?self
    {
        return (new IntervalSet($this))->intersect($other)->first();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function gap(self $other): ?self
    {
        return (new IntervalSet($this, $other))->gaps()->first();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function union(self $other): IntervalSet
    {
        return (new IntervalSet($this))->union($other);
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function difference(self $other): IntervalSet
    {
        return (new IntervalSet($this))->difference($other);
    }

    /**
     * @return array{0: array{start: Time, duration: Duration}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['start' => $this->start, 'duration' => $this->duration], []];
    }

    /**
     * @param array{0: array{start: Time, duration: Duration}, 1: array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->start = $properties['start'];
        $this->duration = $properties['duration'];
        $this->linearStart = (int) $this->start->toUnitOfDay(Unit::Microsecond);
        $this->linearEnd = $this->linearStart + (int) $properties['duration']->total(Unit::Microsecond);
        $this->end = Time::fromUnitOfDay($this->linearEnd, Unit::Microsecond);
        $this->type = $this->setType();
    }
}
