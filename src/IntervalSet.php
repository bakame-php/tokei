<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Closure;
use DateTimeInterface;
use Traversable;

use function array_column;
use function array_key_last;
use function array_map;
use function array_pop;
use function array_shift;
use function count;
use function in_array;
use function max;
use function min;
use function usort;

/**
 * @phpstan-import-type NativeInterval from Interval
 *
 * @implements TemporalSet<Interval>
 */
final class IntervalSet implements TemporalSet
{
    /** @var list<Interval> */
    private readonly array $items;
    private readonly Duration $duration;
    /** @var array<non-empty-string, TemporalSearch<Interval>> */
    private array $engine;

    /**
     * @param Interval|IntervalSet<Interval>|Task|TaskSet<Task> ...$items
     *
     * @throws InvalidDuration
     */
    public function __construct(Interval|IntervalSet|Task|TaskSet ...$items)
    {
        $this->items = self::flatten(...$items);
        $this->duration = Duration::zero()->sum(...array_column($this->items, 'duration'));
    }

    /**
     * @return TemporalSearch<Interval>
     */
    private function engine(Bound $using = Bound::Start): TemporalSearch
    {
        if (!isset($this->engine[$using->name])) {
            /** @var TemporalSearch<Interval> $engine */
            $engine = TemporalSearch::forIntervals($this, $using);

            $this->engine[$using->name] = $engine;
        }

        return $this->engine[$using->name];
    }

    /**
     * Returns a new instance with its intervals ordered by ascending start time.
     *
     * @throws InvalidDuration
     */
    public static function chronological(Interval|IntervalSet|Task|TaskSet ...$items): self
    {
        return new self(...$items)->sorted();
    }

    public static function fromTasks(TaskSet|Task ...$items): self
    {
        return new self(...new TaskSet(...$items)->map(static fn (Task $task): Interval => $task->period));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    /**
     * @return list<Interval>
     */
    public function jsonSerialize(): array
    {
        return $this->all();
    }

    /**
     * @return list<Interval>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function duration(): Duration
    {
        return $this->duration;
    }

    /**
     * @throws InvalidTime
     *
     * @return list<non-empty-string>
     */
    public function formatAll(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): array
    {
        return array_map(static fn (Interval $item): string => $item->format($format, $unit), $this->items);
    }

    /**
     * @return list<NativeInterval>
     */
    public function allNative(DateTimeInterface $reference): array
    {
        return array_map(static fn (Interval $item): array => $item->toNative($reference), $this->items);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * Returns the interval at the given position.
     *
     * Supports negative offsets, where -1 refers to the last interval.
     *
     * @throws TokeiException If the offset is out of range.
     */
    public function get(int $offset): Interval
    {
        return $this->nth($offset) ?? throw TokeiException::dueToInvalidOffset($offset, self::class);
    }

    /**
     * Returns the interval at the given position, or null if it does not exist.
     *
     * Supports negative offsets, where -1 refers to the last interval.
     */
    public function nth(int $offset): ?Interval
    {
        $count = count($this->items);
        if ($offset < 0) {
            $offset = $count + $offset;
        }

        return ($offset < 0 || $offset >= $count) ? null : $this->items[$offset];
    }

    /**
     * Tells whether the given interval is present in the set.
     */
    public function has(Interval|Task ...$items): bool
    {
        $check = new self(...$items);

        return !$check->isEmpty()
            && $check->every(fn (Interval $item): bool => null !== $this->indexOf($item));
    }

    public function indexOf(Interval $interval): ?int
    {
        return array_find_key($this->items, fn (Interval $item) => $interval->equals($item));
    }

    public function lastIndexOf(Interval $interval): ?int
    {
        for ($offset = count($this->items) - 1; $offset >= 0; --$offset) {
            if ($interval->equals($this->items[$offset])) {
                return $offset;
            }
        }

        return null;
    }

    public function first(): ?Interval
    {
        return $this->nth(0);
    }

    public function last(): ?Interval
    {
        return $this->nth(-1);
    }

    /**
     * @throws InvalidDuration
     */
    public function push(Interval|IntervalSet|Task|TaskSet ...$items): self
    {
        $items = self::flatten(...$items);

        return [] === $items ? $this : new self(...$this->items, ...$items);
    }

    /**
     * @throws InvalidDuration
     */
    public function unshift(Interval|IntervalSet|Task|TaskSet ...$items): self
    {
        $set = new self(...$items);

        return $set->isEmpty() ? $this : $set->push($this);
    }

    /**
     * @throws InvalidDuration
     * @throws TimeException
     */
    public function replace(int $offset, Interval|Task $item): self
    {
        if ($offset < 0) {
            $offset += count($this->items);
        }

        isset($this->items[$offset]) || throw TimeException::dueToInvalidOffset($offset, self::class);

        $intervals = $this->items;
        $intervals[$offset] = $item instanceof Task ? $item->period : $item;

        return new self(...$intervals);
    }

    /**
     * @throws InvalidDuration
     */
    public function remove(int ...$offsets): self
    {
        if ([] === $offsets) {
            return $this;
        }

        $nbIntervals = count($this->items);
        $normalized = [];
        foreach ($offsets as $offset) {
            if ($offset < 0) {
                $offset += $nbIntervals;
            }

            if (0 > $offset || $nbIntervals <= $offset) {
                continue;
            }

            $normalized[] = $offset;
        }

        if ([] === $normalized) {
            return $this;
        }

        return $this->filter(static fn (Interval $item, int $index): bool => !in_array($index, $normalized, true)); /* @phpstan-ignore-line */
    }

    /**
     * @return list<Interval>
     */
    private static function flatten(Interval|IntervalSet|Task|TaskSet ...$items): array
    {
        $res = [];
        foreach ($items as $item) {
            $res = [...$res, ...match (true) {
                $item instanceof Interval => [$item],
                $item instanceof TaskSet => self::fromTasks($item)->items,
                $item instanceof Task => [$item->period],
                default => $item->items,
            }];
        }

        return $res;
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function firstMatching(callable $predicate): ?Interval
    {
        return $this->engine()->firstMatching($predicate);
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function lastMatching(callable $predicate): ?Interval
    {
        return $this->engine()->lastMatching($predicate);
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        return $this->engine()->any($predicate);
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function every(callable $predicate): bool
    {
        return $this->engine()->every($predicate);
    }

    public function next(Time|Event $atOrAfter, SearchMode $mode, Bound $using = Bound::Start): self
    {
        return new self(...$this->engine($using)->next($atOrAfter, $mode));
    }

    public function previous(Time|Event $before, SearchMode $mode, Bound $using = Bound::Start): self
    {
        return new self(...$this->engine($using)->previous($before, $mode));
    }

    public function nearest(Time|Event $around, Bound $using = Bound::Start): self
    {
        return new self(...$this->engine($using)->nearest($around));
    }

    public function shift(Duration $duration): self
    {
        return $duration->isZero()
            ? $this
            : $this->transform(fn (Interval $interval): Interval => $interval->shift($duration));
    }

    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        return $this->transform(static fn (Interval $interval): Interval => $interval->roundTo($unit, $mode));
    }

    public function roundDurationTo(Unit $unit, SnapMode $mode = SnapMode::Nearest, Bound $anchor = Bound::Start): self
    {
        return $this->transform(static fn (Interval $interval): Interval => $interval->roundDurationTo($unit, $mode, $anchor));
    }

    /**
     * @template TValue
     *
     * @param callable(Interval, int=): TValue $callback
     *
     * @return iterable<TValue>
     */
    public function map(callable $callback): iterable
    {
        foreach ($this->items as $offset => $item) {
            yield $callback($item, $offset);
        }
    }

    /**
     * Transforms each Interval in the set using the given callback
     * and returns a new IntervalSet containing the resulting Intervals.
     *
     * This is a structure-preserving map operation:
     * - The number of intervals is preserved
     * - The result remains an IntervalSet
     * - The callback must return a valid Interval for each input
     *
     * Unlike map(), which yields a generic iterable of values,
     * transform() rewraps the result into an IntervalSet.
     *
     * @param callable(Interval, int=): (Interval|IntervalSet) $callback
     *
     *
     * @throws InvalidDuration If any produced Interval is invalid
     */
    public function transform(callable $callback): self
    {
        return new self(...$this->map($callback));
    }

    /**
     * @param callable(Interval, int=): bool $callback
     *
     * @throws InvalidDuration
     */
    public function filter(callable $callback): self
    {
        $data = [];
        foreach ($this->items as $offset => $item) {
            if (true === $callback($item, $offset)) {
                $data[] = $item;
            }
        }

        return $data === $this->items ? $this : new self(...$data);
    }

    /**
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, Interval, int=): TReduceReturnType $callback
     * @param TReduceInitial $initial
     *
     * @return TReduceInitial|TReduceReturnType
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;

        foreach ($this->items as $offset => $item) {
            $result = $callback($result, $item, $offset);
        }

        return $result;
    }

    /**
     * Iterates over all intervals in this set.
     *
     * The callback receives the current Interval and its index.
     * If the callback returns false, iteration stops immediately.
     *
     * @param callable(Interval, int=): mixed $callback
     *
     * @return bool True if iteration completed, false if it was stopped early by the callback.
     */
    public function each(callable $callback): bool
    {
        foreach ($this->items as $offset => $item) {
            if (false === $callback($item, $offset)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function gaps(): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $result = [];
        $previous = null;
        foreach ($this->union()->sorted() as $interval) {
            if (null !== $previous && $interval->start->isAfter($previous->end)) {
                $result[] = Interval::between($previous->end, $interval->start);
            }

            $previous = $interval;
        }

        return new self(...$result);
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function difference(Interval|IntervalSet|Task|TaskSet ...$others): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $other = self::chronological(...$others)->union();
        if ($other->isEmpty()) {
            return $this;
        }

        $differences = [];
        $otherIntervals = $other->items;
        foreach ($this->union()->items as $item) {
            if (IntervalType::Collapsed === $item->type) {
                continue;
            }

            $current = IntervalType::Circular !== $item->type
                ? [[$item->linearStart, $item->linearEnd]]
                : [[0, UnitTransformer::toMicroseconds(1, Unit::Day)]];

            foreach ($otherIntervals as $otherInterval) {
                if (IntervalType::Collapsed === $otherInterval->type) {
                    continue;
                }

                $bStart = $otherInterval->linearStart;
                $bEnd = $otherInterval->linearEnd;
                $next = [];
                foreach ($current as [$start, $end]) {
                    if ($bEnd <= $start || $bStart >= $end) {
                        $next[] = [$start, $end];
                        continue;
                    }

                    if ($bStart > $start) {
                        $next[] = [$start, $bStart];
                    }

                    if ($bEnd < $end) {
                        $next[] = [$bEnd, $end];
                    }
                }

                $current = $next;
                if ([] === $current) {
                    break;
                }
            }

            $differences = [...$differences, ...$current];
        }

        return new self(
            ...array_map(
                static fn (array $span): Interval => Interval::fromLinearSpan($span[0], $span[1]),
                $differences,
            )
        );
    }

    /**
     * @throws InvalidInterval|InvalidDuration
     */
    public function intersect(Interval|IntervalSet|Task|TaskSet ...$others): self
    {
        $other = self::chronological(...$others)->union();
        if ($other->isEmpty()) {
            return $this;
        }

        $intersections = [];
        $bSpans = $other->items;
        foreach ($this->union()->items as $aItem) {
            foreach ($bSpans as $bItem) {
                $start = max($aItem->linearStart, $bItem->linearStart);
                $end = min($aItem->linearEnd, $bItem->linearEnd);
                if ($start < $end) {
                    $intersections[] = Interval::fromLinearSpan($start, $end);
                }
            }
        }

        return new self(...$intersections)->union();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function complement(): self
    {
        return new self(Interval::fullDay())->difference($this)->union();
    }

    /**
     * @throws InvalidInterval|InvalidDuration
     */
    public function union(Interval|IntervalSet|Task|TaskSet ...$others): self
    {
        $set = $this->push(...$others)->sorted();
        if (1 >= count($set)) {
            return $set;
        }

        $merged = [];
        foreach ($set->items as $item) {
            if ([] !== $merged) {
                $lastIndex = array_key_last($merged);
                $prevSpan = $merged[$lastIndex];
                if ($item->linearStart <= $prevSpan->linearEnd) {
                    $merged[$lastIndex] = Interval::fromLinearSpan($prevSpan->linearStart, max($prevSpan->linearEnd, $item->linearEnd));
                    continue;
                }
            }

            $merged[] = $item;
        }

        if (count($merged) >= 2) {
            $first = $merged[0];
            $last = $merged[array_key_last($merged)];
            if ($first->overlaps($last)) {
                array_shift($merged);
                array_pop($merged);

                $merged[] = Interval::fromLinearSpan($last->linearStart, $first->linearEnd + Unit::Day->inMicroseconds());
            }
        }

        return new self(...$merged);
    }

    /***
     * @param callable(Interval, Interval): int $callback
     *
     * @throws InvalidDuration
     */
    public function sortedUsing(callable $callback): self
    {
        if (1 >= count($this->items)) {
            return $this;
        }

        $intervals = $this->items;
        usort($intervals, $callback);

        return $intervals === $this->items ? $this : new self(...$intervals);
    }

    /**
     * Sorts the set using each Interval starting or ending time.
     *
     * @throws InvalidDuration
     */
    public function sorted(Bound $by = Bound::Start, Direction $direction = Direction::Ascending): self
    {
        return $this->sortedUsing(self::filterCompare($by, $direction));
    }

    /**
     * Split the interval set into its smallest non-overlapping intervals.
     *
     * @throws InvalidDuration|InvalidInterval
     */
    public function atomic(): self
    {
        return $this
            ->union()
            ->transform(
                fn (Interval $interval): IntervalSet => $interval->splitAt(...$this->atomicBoundaries())
            );
    }

    /**
     * Returns all unique interval start/end boundaries sorted chronologically.
     *
     * @throws InvalidDuration
     *
     * @return list<Time>
     */
    public function atomicBoundaries(): array
    {
        $boundaries = [];

        foreach ($this->sorted() as $interval) {
            $boundaries[(int) $interval->start->toOffset(Unit::Microsecond)] = $interval->start;
            $boundaries[(int) $interval->end->toOffset(Unit::Microsecond)] = $interval->end;
        }

        ksort($boundaries);

        return array_values($boundaries);
    }

    /**
     * @return Closure(Interval, Interval): int
     */
    private static function filterCompare(Bound $bound, Direction $direction): Closure
    {
        $directionFactor = Direction::Ascending === $direction ? 1 : -1;
        $keyExtractor = match ($bound) {
            Bound::Start => static fn (Interval $i): int => $i->linearStart,
            Bound::End => static fn (Interval $i): int => $i->linearEnd,
        };

        $durationComparator = static fn (Interval $a, Interval $b): int => $a->duration->compareTo($b->duration);

        return static function (Interval $a, Interval $b) use ($keyExtractor, $directionFactor, $durationComparator): int {
            $result = ($keyExtractor($a) <=> $keyExtractor($b)) * $directionFactor;

            return 0 !== $result ? $result : $durationComparator($a, $b);
        };
    }

    /**
     * @return array{0: array{intervals: list<Interval>}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['intervals' => $this->items], []];
    }

    /**
     * @param array{0: array{intervals: list<Interval>}, 1: array{}} $data
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->items = $properties['intervals'];
        $this->duration = Duration::zero()->sum(...array_column($this->items, 'duration'));
    }
}
