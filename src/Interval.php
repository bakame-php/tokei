<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Bakame\Tokei\Internal\InputNormalizer;
use Bakame\Tokei\Internal\UnitTransformer;
use DateInterval;
use DateTimeInterface;
use JsonSerializable;
use Time\Duration as TimeDuration;

use function array_map;
use function filter_var;
use function is_string;
use function preg_match;
use function trim;

use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

/**
 * Represents a start-inclusive, end-exclusive interval between two times on a 24-hour circular clock.
 */
final readonly class Interval implements JsonSerializable
{
    private const string REGEXP_ISO80000 = '/^\[(?<start>[^,)]*),(?<end>[^,)]*)\)$/';
    private const string REGEXP_BOURBAKI = '/^\[(?<start>[^,\[]*),(?<end>[^,\[]*)\[$/';
    private const string REGEXP_ISO8601 = '/^(?<start>[^\/]+)\/(?<end>[^\/]+)$/';

    public Time $end;
    public IntervalType $type;

    private function __construct(public Time $start, public Duration $duration)
    {
        $this->end = $this->start->add($this->duration);
        $this->type = $this->setType();
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
        $this->end = Time::sinceMidnight($this->start->offset()->add($this->duration));
        $this->type = $this->setType();
    }

    private function setType(): IntervalType
    {
        return match (Duration::compare($this->start, $this->end)) {
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
    public static function since(
        Time|Event|DateTimeInterface $start,
        Duration|DateInterval|Interval|Task|TimeDuration $duration
    ): self {
        $start = InputNormalizer::time($start);

        return new self($start, $start->distance($start->add(InputNormalizer::duration($duration))));
    }

    /**
     * Returns a new instance from an end time and a duration.
     *
     * The end time is not included in the interval
     *
     * @throws InvalidDuration
     */
    public static function until(
        Time|Event|DateTimeInterface $end,
        Duration|DateInterval|Interval|Task|TimeDuration $duration
    ): self {
        $end = InputNormalizer::time($end);
        $start = $end->sub(InputNormalizer::duration($duration));

        return new self($start, $start->distance($end));
    }

    /**
     * Returns a new instance where time represents the interval mid-time and a given duration.
     *
     * @throws InvalidDuration
     */
    public static function around(
        Time|Event|DateTimeInterface $midRange,
        Duration|DateInterval|Interval|Task|TimeDuration $duration
    ): self {
        $midRange = InputNormalizer::time($midRange);
        $duration = InputNormalizer::duration($duration);

        $start = $midRange->add($duration->divideBy(2)->negate());

        return self::between($start, $start->add($duration));
    }

    /**
     * Returns a new instance from a starting and an ending time.
     *
     * The end time is not included in the interval
     *
     * @throws InvalidDuration
     */
    public static function between(
        Time|Event|DateTimeInterface $start,
        Time|Event|DateTimeInterface $end
    ): self {
        $start = InputNormalizer::time($start);
        $end = InputNormalizer::time($end);

        return new self($start, $start->distance($end));
    }

    /**
     * @see IntervalFormat::decode()
     *
     * @throws InvalidInterval
     */
    public static function fromFormat(
        string $notation,
        IntervalFormat $format,
        ?Unit $unit = null
    ): self {
        $trimmedData = trim($notation);
        $pattern = match ($format) {
            IntervalFormat::Bourbaki => self::REGEXP_BOURBAKI,
            IntervalFormat::Iso80000 => self::REGEXP_ISO80000,
            default => self::REGEXP_ISO8601,
        };

        1 === preg_match($pattern, $trimmedData, $found) || throw InvalidInterval::dueToMalformedFormat($notation, $format);

        $start = trim($found['start']);
        $end = trim($found['end']);

        '' !== $start || '' !== $end || throw InvalidInterval::dueToMalformedFormat($notation, $format);

        try {
            return match ($format) {
                IntervalFormat::Bourbaki,
                IntervalFormat::Iso80000 => self::parseMathInterval($start, $end, $notation, $unit, $format),
                default => self::parseIso8601Interval($start, $end, $notation, $format),
            };
        } catch (TimeException $exception) {
            $exception instanceof InvalidInterval ? throw $exception : throw InvalidInterval::dueToMalformedFormat($notation, $format, $exception);
        }
    }

    /**
     * @throws InvalidInterval|InvalidTime|InvalidDuration
     */
    private static function parseMathInterval(string $start, string $end, string $data, ?Unit $unit, IntervalFormat $format): Interval
    {
        $start = self::normalizeMathIntervalValue($start);
        $end = self::normalizeMathIntervalValue($end);

        $start ??= is_string($end) ? '00:00' : 0;
        $end ??= is_string($start) ? '00:00' : 0;

        (get_debug_type($start) === get_debug_type($end))
        || is_string($start)
        || null !== $unit
        || throw InvalidInterval::dueToMalformedFormat($data, $format);

        return Interval::between(
            self::createTime($start, $unit, $data, $format),
            self::createTime($end, $unit, $data, $format),
        );
    }

    private static function normalizeMathIntervalValue(string $value): int|float|string|null
    {
        if ('' === $value) {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if (false !== $intValue) {
            return $intValue;
        }

        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);
        if (false !== $floatValue) {
            return $floatValue;
        }

        return $value;
    }

    /**
     * @throws InvalidInterval|InvalidTime
     */
    private static function createTime(int|string|float $value, ?Unit $unit, string $data, IntervalFormat $format): Time
    {
        if (is_string($value)) {
            return Time::fromFormat($value, TimeFormat::Clock);
        }

        null !== $unit || throw InvalidInterval::dueToMalformedFormat($data, $format);

        /** @var non-negative-int $nanoseconds */
        $nanoseconds = UnitTransformer::toTicks($value, $unit);

        return Time::sinceMidnight(Duration::of(nanoseconds: $nanoseconds));
    }

    /**
     * @throws TokeiException
     */
    private static function parseIso8601Interval(string $start, string $end, string $notation, IntervalFormat $format): Interval
    {
        $isDurationFormat = static fn (string $str): bool => str_starts_with($str, 'P') || str_starts_with($str, '-P');

        return match (true) {
            IntervalFormat::Iso8601DurationEnd === $format && $isDurationFormat($start) => Interval::until(Time::fromFormat($end, TimeFormat::Clock), Duration::fromFormat($start, DurationFormat::Iso8601)),
            IntervalFormat::Iso8601StartDuration === $format && $isDurationFormat($end) => Interval::since(Time::fromFormat($start, TimeFormat::Clock), Duration::fromFormat($end, DurationFormat::Iso8601)),
            IntervalFormat::Iso8601StartEnd === $format => Interval::between(Time::fromFormat($start, TimeFormat::Clock), Time::fromFormat($end, TimeFormat::Clock)),
            default => throw InvalidInterval::dueToMalformedFormat($notation, $format),
        };
    }

    /**
     * @internal Used internally by IntervalSet to reconstruct an Interval from
     *           its linear representation. This method is not part of the public API.
     *
     * @throws TokeiException
     */
    public static function fromLinearSpan(Duration $linearStart, Duration $linearEnd): self
    {
        $duration = $linearEnd->sub($linearStart);
        -1 !== $duration->sign || throw new InvalidInterval('Invalid linear span: the start must be shorter or equal to the end linear span.');

        return new self(Time::sinceMidnight($linearStart->absolute()), $duration);
    }

    /**
     * Returns the full circular interval starting at midnight.
     */
    public static function fullDay(): self
    {
        return self::circular(Time::midnight());
    }

    public static function circular(Event|Time|DateTimeInterface $at): self
    {
        return new self(InputNormalizer::time($at), Duration::fullDay());
    }

    public static function collapsed(Event|Time|DateTimeInterface $at): self
    {
        return new self(InputNormalizer::time($at), Duration::zero());
    }

    /**
     * @see IntervalFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(IntervalFormat $format, ?Unit $unit = null): string
    {
        $formatTime = static fn (Time $time, ?Unit $unit, IntervalFormat $format): string =>
                 (null === $unit || !$format->supportsUnit())
                    ? $time->format(TimeFormat::Clock)
                    : $time->offset()->toNumberString($unit, 6);

        $start = $formatTime($this->start, $unit, $format);
        $end = $formatTime($this->end, $unit, $format);

        return match ($format) {
            IntervalFormat::Iso8601StartDuration => $start.'/'.$this->duration->format(DurationFormat::Iso8601),
            IntervalFormat::Iso8601DurationEnd => $this->duration->format(DurationFormat::Iso8601).'/'.$end,
            IntervalFormat::Iso8601StartEnd => $start.'/'.$end,
            IntervalFormat::Iso80000 => '['.$start.','.$end.')',
            IntervalFormat::Bourbaki => '['.$start.','.$end.'[',
        };
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
        return $this->format(IntervalFormat::Iso8601StartDuration);
    }

    /**
     * Returns a new instance with a new start time.
     *
     * @throws InvalidDuration
     */
    public function startingOn(Event|Time|DateTimeInterface $time): self
    {
        $time = InputNormalizer::time($time);

        return $time->equals($this->start) ? $this : self::between($time, $this->end);
    }

    /**
     * Returns a new instance with a new end time.
     *
     * @throws TokeiException
     */
    public function endingOn(Event|Time|DateTimeInterface $time): self
    {
        $time = InputNormalizer::time($time);

        return $time->equals($this->end) ? $this : self::between($this->start, $time);
    }

    /**
     * Returns a new instance with both endpoints shifted by duration.
     *
     * @throws TokeiException
     */
    public function add(Duration|DateInterval|Interval|Task|TimeDuration $duration): self
    {
        $duration = InputNormalizer::duration($duration);

        return $duration->isZero() ? $this : self::between($this->start->add($duration), $this->end->add($duration));
    }

    /**
     * Returns a new instance with both endpoints shifted by duration.
     *
     * @throws TokeiException
     */
    public function sub(Duration|DateInterval|Interval|Task|TimeDuration $duration): self
    {
        $duration = InputNormalizer::duration($duration);

        return $duration->isZero() ? $this : self::between($this->start->sub($duration), $this->end->sub($duration));
    }

    /**
     * Returns a new instance with a specified endpoint shifted by duration.
     *
     * The shifted endpoint is specified by the bound.
     *
     * @throws TokeiException
     */
    public function shiftBound(Duration|DateInterval|Interval|Task|TimeDuration $duration, Bound $to): self
    {
        $duration = InputNormalizer::duration($duration);

        return match (true) {
            $duration->isZero() => $this,
            Bound::Start === $to => self::between($this->start->add($duration), $this->end),
            Bound::End === $to => self::between($this->start, $this->end->add($duration)),
        };
    }

    /**
     * Returns a new instance with a specified endpoint shifted by duration.
     *
     * The specified endpoint is not shifted; the other is.
     *
     * @throws TokeiException
     */
    public function lasting(Duration|DateInterval|Interval|Task|TimeDuration $duration, Bound $from): self
    {
        $duration = InputNormalizer::duration($duration);

        return match (true) {
            $duration->isZero() => $this,
            Bound::Start === $from => self::between($this->start, $this->start->add($duration)),
            Bound::End === $from => self::between($this->end->add($duration->negate()), $this->end),
        };
    }

    /**
     * Expands or shrinks the interval duration.
     *
     * The result is based on the specified duration sign.
     *
     * @throws TokeiException
     */
    public function expand(Duration|DateInterval|Interval|Task|TimeDuration $duration): self
    {
        $duration = InputNormalizer::duration($duration);

        $new = self::between($this->start->add($duration->negate()), $this->end->add($duration));

        return $new->equals($this) ? $this : $new;
    }

    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $new = self::between($this->start->roundTo($unit, $mode), $this->end->roundTo($unit, $mode));

        return $new->equals($this) ? $this : $new;
    }

    public function roundDurationTo(Unit $unit, SnapMode $mode = SnapMode::Nearest, Bound $anchor = Bound::Start): self
    {
        $duration = $this->duration->roundTo($unit, $mode);
        $new = Bound::Start === $anchor ? self::since($this->start, $duration) : self::until($this->end, $duration);

        return $new->equals($this) ? $this : $new;
    }

    /**
     * @throws InvalidDuration
     */
    public function complement(): self
    {
        $new = match ($this->type) {
            IntervalType::Collapsed => self::circular($this->start),
            IntervalType::Circular => self::collapsed($this->start),
            default => self::between($this->end, $this->start),
        };

        return $new->equals($this) ? $this : $new;
    }

    /**
     * Yields the start time of each sub-interval produced by splitting this
     * interval by the given duration.
     *
     * @throws TokeiException
     *
     * @return iterable<Time>
     */
    public function steps(
        Duration|DateInterval|Interval|Task|TimeDuration $duration,
        Bound $from = Bound::Start
    ): iterable {
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
     * @throws TokeiException
     */
    public function splitBy(
        Duration|DateInterval|Interval|Task|TimeDuration $duration,
        Bound $from = Bound::Start
    ): IntervalSet {
        $duration = InputNormalizer::duration($duration);
        1 === $duration->sign || throw new InvalidDuration('The duration can not be negative or equal to 0.');
        if (IntervalType::Collapsed === $this->type) {
            return new IntervalSet();
        }

        $forward = Bound::Start === $from;
        $cursor = ($forward ? $this->start : $this->end);
        $limit = ($forward ? $this->end : $this->start);
        $result = [];
        while ($forward ? $cursor->isBefore($limit) : $cursor->isAfter($limit)) {
            $next = $forward ? Time::minOf($cursor->add($duration), $limit) : Time::maxOf($cursor->sub($duration), $limit);
            $result[] = $forward ? self::between($cursor, $next) : self::between($next, $cursor);

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
    public function splitAt(Event|Time|DateTimeInterface ...$steps): IntervalSet
    {
        $res = array_filter(array_map(InputNormalizer::time(...), $steps), fn ($step): bool => $this->includes($step));
        usort($res, fn (Time $a, Time $b): int => Duration::compare($this->start->distance($a), $this->start->distance($b)));

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

    public function sameDurationAs(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 === Duration::compare($this, $other);
    }

    public function isLongerThan(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 < Duration::compare($this, $other);
    }

    public function isLongerThanOrEqual(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 <= Duration::compare($this, $other);
    }

    public function isShorterThan(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 > Duration::compare($this, $other);
    }

    public function isShorterThanOrEqual(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 >= Duration::compare($this, $other);
    }

    public function equals(Interval|Task $other): bool
    {
        $other = InputNormalizer::interval($other);

        return $this->start->equals($other->start)
            && $this->duration->equals($other->duration);
    }

    public function includes(Event|Time|DateTimeInterface $time): bool
    {
        if (IntervalType::Circular === $this->type) {
            return true;
        }

        if (IntervalType::Collapsed === $this->type) {
            return false;
        }

        $sinceMidnight = InputNormalizer::time($time)->offset();
        $linearStart = $this->start->offset();
        $linearEnd = $linearStart->add($this->duration);
        if ($linearEnd->isLongerThan($linearStart) && $sinceMidnight->isShorterThan($linearStart)) {
            $sinceMidnight = $sinceMidnight->add(Duration::fullDay());
        }

        return $sinceMidnight->isLongerThanOrEqual($linearStart)
            && $sinceMidnight->isShorterThan($linearEnd);
    }

    public function contains(Interval|Task $other): bool
    {
        $other = InputNormalizer::interval($other);

        return $this->includes($other->start)
            && ($this->includes($other->end) || $this->end->equals($other->end));
    }

    public function overlaps(Interval|Task $other): bool
    {
        $other = InputNormalizer::interval($other);

        return $this->includes($other->start)
            || $other->includes($this->start);
    }

    public function abuts(Interval|Task $other): bool
    {
        $other = InputNormalizer::interval($other);

        return $this->start->equals($other->end)
            || $this->end->equals($other->start);
    }

    /**
     * @throws InvalidInterval|InvalidDuration
     */
    public function intersect(Interval|Task $other): ?self
    {
        return new IntervalSet($this)->intersect(InputNormalizer::interval($other))->first();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function gap(Interval|Task $other): ?self
    {
        return new IntervalSet($this, InputNormalizer::interval($other))->gaps()->first();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function union(Interval|Task $other): IntervalSet
    {
        return new IntervalSet($this)->union(InputNormalizer::interval($other));
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function difference(Interval|Task $other): IntervalSet
    {
        return new IntervalSet($this)->difference(InputNormalizer::interval($other));
    }
}
