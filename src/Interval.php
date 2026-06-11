<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

use function array_map;

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

    private function __construct(Time|Event $start, Duration $duration)
    {
        $this->start = self::extractTime($start);
        $this->duration = $duration;
        $this->linearStart = (int) $this->start->toOffset(Unit::Microsecond);
        $this->linearEnd = $this->linearStart + (int) $duration->total(Unit::Microsecond);
        $this->end = Time::fromOffset($this->linearEnd, Unit::Microsecond);
        $this->type = $this->setType();
    }

    private static function extractTime(Time|Event $time): Time
    {
        return $time instanceof Event ? $time->at : $time;
    }

    private static function extractInterval(Interval|Task $interval): Interval
    {
        return $interval instanceof Task ? $interval->period : $interval;
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
     * Returns a new instance from a start time and a duration.
     *
     * @throws InvalidDuration
     */
    public static function since(Time|Event $start, Duration $duration): self
    {
        $start = self::extractTime($start);

        return new self($start, $start->distance($start->shift($duration)));
    }

    /**
     * Returns a new instance from an end time and a duration.
     *
     * The end time is not included in the interval
     *
     * @throws InvalidDuration
     */
    public static function until(Time|Event $end, Duration $duration): self
    {
        $end = self::extractTime($end);
        $start = $end->shift($duration->negated());

        return new self($start, $start->distance($end));
    }

    /**
     * Returns a new instance where time represents the interval mid-time and a given duration.
     *
     * @throws InvalidDuration
     */
    public static function around(Time|Event $midRange, Duration $duration): self
    {
        $midRange = self::extractTime($midRange);
        $start = $midRange->shift($duration->dividedBy(2)->negated());

        return self::between($start, $start->shift($duration));
    }

    /**
     * Returns a new instance from a starting and an ending time.
     *
     * The end time is not included in the interval
     *
     * @throws InvalidDuration
     */
    public static function between(Time|Event $start, Time|Event $end): self
    {
        $start = self::extractTime($start);
        $end = self::extractTime($end);
        return new self($start, $start->distance(self::extractTime($end)));
    }

    /**
     * @see IntervalFormat::decode()
     *
     * @throws InvalidInterval
     */
    public static function fromFormat(string $value, IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): self
    {
        return $format->decode($value, $unit);
    }

    /**
     * Returns a new instance from linear start end ending point.
     *
     * @param int $linearStart the starting time represented on a linear span in microseconds
     * @param int $linearEnd the ending time represented on a linear span in microseconds
     *
     * @throws InvalidInterval|InvalidDuration
     */
    public static function fromLinearSpan(int $linearStart, int $linearEnd): self
    {
        $duration = $linearEnd - $linearStart;

        0 <= $duration || throw new InvalidInterval('Invalid linear span: the start must be shorter or equal to the end linear span.');

        return new self(Time::fromOffset($linearStart, Unit::Microsecond), Duration::of(microseconds: $duration));
    }

    /**
     * Returns a Circular interval using midnight as endpoint.
     */
    public static function fullDay(): self
    {
        /** @var ?self $interval */
        static $interval = null;

        return $interval ??= self::circular(Time::midnight());
    }

    public static function circular(Time $at): self
    {
        return new self($at, Duration::of(days: 1));
    }

    public static function collapsed(Time $at): self
    {
        return new self($at, Duration::zero());
    }

    /**
     * @see IntervalFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): string
    {
        return $format->encode($this, $unit);
    }

    /**
     * Converts this interval to a native PHP representation consisting of
     * a start date and a DateInterval duration.
     *
     * The start date is obtained by applying the interval's start offset
     * to the given reference date.
     *
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
     * @see self::format()
     *
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * Returns a new instance with a new start time.
     *
     * @throws InvalidDuration
     */
    public function startingOn(Time|Event $time): self
    {
        $time = self::extractTime($time);

        return $time->equals($this->start) ? $this : self::between($time, $this->end);
    }

    /**
     * Returns a new instance with a new end time.
     *
     * @throws InvalidDuration
     */
    public function endingOn(Time|Event $time): self
    {
        $time = self::extractTime($time);

        return $time->equals($this->end) ? $this : self::between($this->start, $time);
    }

    /**
     * Returns a new instance with both endpoints shifted by duration.
     *
     * @throws InvalidDuration
     */
    public function shift(Duration $duration): self
    {
        return $duration->isZero() ? $this : self::between($this->start->shift($duration), $this->end->shift($duration));
    }

    /**
     * Returns a new instance with a specified endpoint shifted by duration.
     *
     * The shifted endpoint is specified by the bound.
     *
     * @throws InvalidDuration
     */
    public function shiftBound(Duration $duration, Bound $to): self
    {
        return match (true) {
            $duration->isZero() => $this,
            Bound::Start === $to => self::between($this->start->shift($duration), $this->end),
            Bound::End === $to => self::between($this->start, $this->end->shift($duration)),
        };
    }

    /**
     * Returns a new instance with a specified endpoint shifted by duration.
     *
     * The specified endpoint is not shifted; the other is.
     *
     * @throws InvalidDuration
     */
    public function lasting(Duration $duration, Bound $from): self
    {
        return match (true) {
            $duration->isZero() => $this,
            Bound::Start === $from => self::between($this->start, $this->start->shift($duration)),
            Bound::End === $from => self::between($this->end->shift($duration->negated()), $this->end),
        };
    }

    /**
     * Expands or shrinks the interval duration.
     *
     * The result is based on the specified duration sign.
     *
     * @throws InvalidDuration
     */
    public function expand(Duration $duration): self
    {
        return self::between($this->start->shift($duration->negated()), $this->end->shift($duration));
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
     * Yields the start time of each sub-interval produced by splitting this
     * interval by the given duration.
     *
     * @throws InvalidDuration
     *
     * @return iterable<Time>
     */
    public function steps(Duration $duration, Bound $from = Bound::Start): iterable
    {
        foreach ($this->splitBy($duration, $from) as $interval) {
            yield $interval->start;
        }
    }

    /**
     * Splits this interval into consecutive sub-intervals of the given
     * duration.
     *
     * If the interval length is not an exact multiple of the duration,
     * one resulting interval may be shorter than the requested duration.
     *
     * The $from parameter determines whether the split starts from the
     * interval start or end boundary.
     *
     * @throws InvalidDuration
     */
    public function splitBy(Duration $duration, Bound $from = Bound::Start): IntervalSet
    {
        1 === $duration->sign || throw new InvalidDuration('The duration can not be negative or equal to 0.');
        if (IntervalType::Collapsed === $this->type) {
            return new IntervalSet();
        }

        $step = $duration->total(Unit::Microsecond);
        $start = $this->start->toOffset(Unit::Microsecond);
        $end = $this->end->toOffset(Unit::Microsecond);
        $forward = Bound::Start === $from;
        $cursor = $forward ? $start : $end;
        $limit = $forward ? $end : $start;
        $result = [];
        while ($forward ? $cursor < $limit : $cursor > $limit) {
            /** @var int $next */
            $next = $forward ? min($cursor + $step, $limit) : max($cursor - $step, $limit);
            $result[] = $forward
                ? self::between(Time::fromOffset($cursor, Unit::Microsecond), Time::fromOffset($next, Unit::Microsecond))
                : self::between(Time::fromOffset($next, Unit::Microsecond), Time::fromOffset($cursor, Unit::Microsecond));

            $cursor = $next;
        }

        return new IntervalSet(...$result);
    }

    /**
     * Splits this interval at the specified times.
     *
     * All steps must be contained within this interval.
     *
     * @throws InvalidDuration
     */
    public function splitAt(Time|Event ...$steps): IntervalSet
    {
        $res = array_map(self::extractTime(...), $steps);
        $res = array_filter($res, fn ($step): bool => $this->includes($step));
        usort($res, fn (Time $a, Time $b): int => $this->start->distance($a)->compareTo($this->start->distance($b)));

        $result = [];
        $cursor = $this->start;
        foreach ($res as $time) {
            $interval = self::since($cursor, $cursor->distance($time));
            if (IntervalType::Collapsed !== $interval->type) {
                $result[] = $interval;
            }
            $cursor = $interval->end;
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

    public function equals(Interval|Task $other): bool
    {
        $other = self::extractInterval($other);

        return $this->start->equals($other->start)
            && $this->duration->equals($other->duration);
    }

    public function includes(Time|Event $time): bool
    {
        if (IntervalType::Circular === $this->type) {
            return true;
        }

        if (IntervalType::Collapsed === $this->type) {
            return false;
        }

        $time = self::extractTime($time);

        $timeInMicro = $time->toOffset(Unit::Microsecond);
        if ($this->linearEnd > $this->linearStart && $timeInMicro < $this->linearStart) {
            $timeInMicro += Unit::Day->inMicroseconds();
        }

        return $timeInMicro >= $this->linearStart
            && $timeInMicro < $this->linearEnd;
    }

    public function contains(Interval|Task $other): bool
    {
        $other = self::extractInterval($other);

        return $this->includes($other->start)
            && ($this->includes($other->end) || $this->end->equals($other->end));
    }

    public function overlaps(Interval|Task $other): bool
    {
        $other = self::extractInterval($other);

        return $this->includes($other->start)
            || $other->includes($this->start);
    }

    public function abuts(Interval|Task $other): bool
    {
        $other = self::extractInterval($other);

        return $this->start->equals($other->end)
            || $this->end->equals($other->start);
    }

    /**
     * @throws InvalidInterval|InvalidDuration
     */
    public function intersect(Interval|Task $other): ?self
    {
        return (new IntervalSet($this))
            ->intersect(self::extractInterval($other))
            ->first();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function gap(Interval|Task $other): ?self
    {
        return (new IntervalSet($this, self::extractInterval($other)))
            ->gaps()
            ->first();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function union(Interval|Task $other): IntervalSet
    {
        return (new IntervalSet($this))->union(self::extractInterval($other));
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function difference(Interval|Task $other): IntervalSet
    {
        return (new IntervalSet($this))->difference(self::extractInterval($other));
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
        $this->linearStart = (int) $this->start->toOffset(Unit::Microsecond);
        $this->linearEnd = $this->linearStart + (int) $properties['duration']->total(Unit::Microsecond);
        $this->end = Time::fromOffset($this->linearEnd, Unit::Microsecond);
        $this->type = $this->setType();
    }
}
