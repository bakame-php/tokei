<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Closure;
use Countable;
use DateTimeInterface;
use IteratorAggregate;
use JsonSerializable;
use SortDirection;
use Traversable;
use TypeError;
use UnitEnum;
use ValueError;

use function array_column;
use function array_key_last;
use function array_map;
use function array_pop;
use function array_shift;
use function count;
use function in_array;
use function is_string;
use function max;
use function min;
use function strtolower;
use function usort;

/**
 * @phpstan-import-type NativeInterval from Interval
 *
 * @implements IteratorAggregate<Interval>
 */
final readonly class IntervalSet implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Interval> */
    private array $intervals;
    private Duration $duration;

    /**
     * @throws InvalidDuration
     */
    public function __construct(Interval|self ...$intervals)
    {
        $this->intervals = self::flatten(...$intervals);
        $this->duration = Duration::zero()->sum(...array_column($this->intervals, 'duration'));
    }

    public function count(): int
    {
        return count($this->intervals);
    }

    public function getIterator(): Traversable
    {
        yield from $this->intervals;
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
        return $this->intervals;
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
    public function allFormatted(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): array
    {
        return array_map(
            static fn (Interval $interval): string => $interval->format($format, $unit),
            $this->intervals
        );
    }

    /**
     * @return list<NativeInterval>
     */
    public function allNative(DateTimeInterface $reference): array
    {
        return array_map(static fn (Interval $interval): array => $interval->toNative($reference), $this->intervals);
    }

    public function isEmpty(): bool
    {
        return [] === $this->intervals;
    }

    /**
     * @throws TimeException
     */
    public function get(int $offset): Interval
    {
        return $this->nth($offset) ?? throw new TimeException('Invalid offset ('.$offset.') given to '.self::class.'.');
    }

    public function nth(int $offset): ?Interval
    {
        if ($offset < 0) {
            $offset += count($this->intervals);
        }

        return $this->intervals[$offset] ?? null;
    }

    /**
     * Tells whether the given interval is present in the set.
     */
    public function has(Interval ...$intervals): bool
    {
        foreach ($intervals as $interval) {
            if (null === $this->indexOf($interval)) {
                return false;
            }
        }

        return [] !== $intervals;
    }

    public function indexOf(Interval $interval): ?int
    {
        foreach ($this->intervals as $offset => $item) {
            if ($item->equals($interval)) {
                return $offset;
            }
        }

        return null;
    }

    public function lastIndexOf(Interval $interval): ?int
    {
        for ($offset = count($this->intervals) - 1; $offset >= 0; --$offset) {
            if ($interval->equals($this->intervals[$offset])) {
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
     * @param callable(Interval, int=): bool $predicate
     */
    public function firstMatching(callable $predicate): ?Interval
    {
        foreach ($this->intervals as $offset => $interval) {
            if (true === $predicate($interval, $offset)) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function lastMatching(callable $predicate): ?Interval
    {
        for ($offset = count($this->intervals) - 1; $offset >= 0; --$offset) {
            $interval = $this->intervals[$offset];
            if (true === $predicate($interval, $offset)) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * @throws InvalidDuration
     */
    public function push(Interval|self ...$interval): self
    {
        $res = self::flatten(...$interval);

        return [] === $res ? $this : new self(...$this->intervals, ...$res);
    }

    /**
     * @throws InvalidDuration
     */
    public function unshift(Interval|self ...$interval): self
    {
        $set = new self(...$interval);

        return $set->isEmpty() ? $this : $set->push($this);
    }

    /**
     * @throws InvalidDuration
     * @throws TimeException
     */
    public function replace(int $offset, Interval $interval): self
    {
        if ($offset < 0) {
            $offset += count($this->intervals);
        }

        isset($this->intervals[$offset]) || throw new TimeException('Invalid offset ('.$offset.') given to '.self::class.'.');

        $intervals = $this->intervals;
        $intervals[$offset] = $interval;

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

        $nbIntervals = count($this->intervals);
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

        return $this->filter(static fn (Interval $interval, int $index): bool => !in_array($index, $normalized, true)); /* @phpstan-ignore-line */
    }

    /**
     * @return list<Interval>
     */
    private static function flatten(Interval|self ...$intervals): array
    {
        $res = [];
        foreach ($intervals as $item) {
            $found = $item instanceof Interval ? [$item] : $item->intervals;
            foreach ($found as $interval) {
                $res[] = $interval;
            }
        }

        return $res;
    }

    /**
     * @param callable(Interval, int=): bool $callback
     */
    public function any(callable $callback): bool
    {
        foreach ($this->intervals as $key => $interval) {
            if (true === $callback($interval, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(Interval, int=): bool $callback
     */
    public function every(callable $callback): bool
    {
        foreach ($this->intervals as $key => $interval) {
            if (true !== $callback($interval, $key)) {
                return false;
            }
        }

        return [] !== $this->intervals;
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
        foreach ($this->intervals as $offset => $interval) {
            yield $callback($interval, $offset);
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
        foreach ($this->intervals as $key => $interval) {
            if (true === $callback($interval, $key)) {
                $data[] = $interval;
            }
        }

        return $data === $this->intervals ? $this : new self(...$data);
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

        foreach ($this->intervals as $key => $interval) {
            $result = $callback($result, $interval, $key);
        }

        return $result;
    }

    /**
     * @param callable(Interval, int=): mixed $callback
     */
    public function each(callable $callback): bool
    {
        foreach ($this->intervals as $key => $interval) {
            if (false === $callback($interval, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function gaps(): IntervalSet
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

        return new IntervalSet(...$result);
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function difference(self|Interval ...$others): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $other = new self(...$others);
        if ($other->isEmpty()) {
            return $this;
        }

        $differences = [];
        $otherIntervals = $other->union()->intervals;
        foreach ($this->union()->intervals as $interval) {
            if (IntervalType::Collapsed === $interval->type) {
                continue;
            }

            $current = IntervalType::Circular !== $interval->type
                ? [[$interval->linearStart, $interval->linearEnd]]
                : [[0, Unit::Day->toMicroseconds(1)]];

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
    public function intersect(self|Interval ...$others): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $other = new self(...$others);
        if ($other->isEmpty()) {
            return $this;
        }

        $intersections = [];
        $bSpans = $other->union()->intervals;
        foreach ($this->union()->intervals as $aInterval) {
            foreach ($bSpans as $bInterval) {
                $start = max($aInterval->linearStart, $bInterval->linearStart);
                $end = min($aInterval->linearEnd, $bInterval->linearEnd);
                if ($start < $end) {
                    $intersections[] = Interval::fromLinearSpan($start, $end);
                }
            }
        }

        return (new self(...$intersections))->union();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function complement(): self
    {
        return (new IntervalSet(Interval::fullDay()))->difference($this)->union();
    }

    /**
     * @throws InvalidInterval|InvalidDuration
     */
    public function union(Interval|self ...$others): self
    {
        $set = $this->push(...$others)->sorted();
        if (1 >= count($set)) {
            return $set;
        }

        $merged = [];
        foreach ($set->intervals as $span) {
            if ([] !== $merged) {
                $lastIndex = array_key_last($merged);
                $prevSpan = $merged[$lastIndex];
                if ($span->linearStart <= $prevSpan->linearEnd) {
                    $merged[$lastIndex] = Interval::fromLinearSpan($prevSpan->linearStart, max($prevSpan->linearEnd, $span->linearEnd));
                    continue;
                }
            }

            $merged[] = $span;
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
        if (1 >= count($this->intervals)) {
            return $this;
        }

        $intervals = $this->intervals;
        usort($intervals, $callback);

        return $intervals === $this->intervals ? $this : new self(...$intervals);
    }

    /**
     * Sorts the set on each Interval starting time.
     *
     * @see https://wiki.php.net/rfc/sort_direction_enum
     *
     * The only Enum supported is the PHP8.6+ SortDirection Enum and its polyfill,
     * otherwise, the strings "asc", "ascending", "desc" and "descending" are
     * supported in a case-insensitive way.
     *
     * @throws InvalidDuration
     */
    public function sorted(Bound $sortBound = Bound::Start, UnitEnum|string $sortDirection = 'asc'): self
    {
        return $this->sortedUsing(self::filterCompare($sortBound, self::filterSortDirection($sortDirection)));
    }

    /**
     * @param 'asc'|'desc' $sortDirection
     *
     * @return Closure(Interval, Interval): int
     */
    private static function filterCompare(Bound $bound, string $sortDirection): Closure
    {
        $primary = match ($bound) {
            Bound::Start => static fn (Interval $x, Interval $y) => $x->linearStart <=> $y->linearStart,
            Bound::End => static fn (Interval $x, Interval $y) => $x->linearEnd <=> $y->linearEnd,
        };

        $primary = 'asc' === $sortDirection ? $primary : static fn (Interval $x, Interval $y) => $primary($y, $x);
        $secondary = static fn (Interval $x, Interval $y): int => $x->duration->compareTo($y->duration);

        return static function (Interval $x, Interval $y) use ($primary, $secondary): int {
            $result = $primary($x, $y);

            return 0 !== $result ? $result : $secondary($x, $y);
        };
    }

    /**
     * @return 'asc'|'desc'
     */
    private static function filterSortDirection(UnitEnum|string $sortDirection): string
    {
        if (enum_exists('\SortDirection') && $sortDirection instanceof SortDirection) {
            return match ($sortDirection) {
                SortDirection::Ascending => 'asc',
                SortDirection::Descending => 'desc',
                default => throw new ValueError("Unknown sort direction '$sortDirection->name'"),
            };
        }

        is_string($sortDirection) || throw new TypeError('Argument ($sortDirection) must be of type SortDirection, '.$sortDirection::class.' given,');
        $sortDirection = strtolower($sortDirection);

        return match ($sortDirection) {
            'asc', 'ascending' => 'asc',
            'desc', 'descending' => 'desc',
            default => throw new ValueError("Unknown sort direction '$sortDirection'")
        };
    }

    /**
     * @return array{0: array{intervals: list<Interval>}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['intervals' => $this->intervals], []];
    }

    /**
     * @param array{0: array{intervals: list<Interval>}, 1: array{}} $data
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->intervals = $properties['intervals'];
        $this->duration = Duration::zero()->sum(...array_column($this->intervals, 'duration'));
    }
}
