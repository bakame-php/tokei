<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Countable;
use IteratorAggregate;
use Traversable;

use function array_key_first;
use function array_key_last;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function array_values;
use function count;
use function max;
use function min;
use function usort;

/**
 * @phpstan-type LinearSpan array{0: int, 1: int}
 *
 * @implements IteratorAggregate<Interval>
 */
final readonly class IntervalSet implements Countable, IteratorAggregate
{
    private const int MICRO_PER_DAY = 24 * 60 * 60 * 1_000_000;

    /** @var list<Interval> */
    private array $intervals;

    public function __construct(Interval|self ...$intervals)
    {
        $this->intervals = self::flatten(...$intervals);
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
    public function all(): array
    {
        return $this->intervals;
    }

    /**
     * @return list<string>
     */
    public function allFormatted(
        string $separator = ':',
        PaddingMode $padding = PaddingMode::Padded,
        SubSecondDisplay $subSecondDisplay = SubSecondDisplay::Auto,
    ): array {
        return array_map(
            fn (Interval $interval): string => $interval->format($separator, $padding, $subSecondDisplay),
            $this->intervals
        );
    }

    public function isEmpty(): bool
    {
        return [] === $this->intervals;
    }

    public function get(int $offset): ?Interval
    {
        if ($offset < 0) {
            $offset += count($this->intervals);
        }

        return $this->intervals[$offset] ?? null;
    }

    public function first(): ?Interval
    {
        return $this->get(0);
    }

    public function last(): ?Interval
    {
        return $this->get(-1);
    }

    public function push(Interval|self ...$interval): self
    {
        $res = self::flatten(...$interval);

        return [] === $res ? $this : new self(...array_merge($this->intervals, $res));
    }

    /**
     * @param Interval|IntervalSet ...$intervals
     *
     * @return list<Interval>
     */
    private static function flatten(Interval|self ...$intervals): array
    {
        $res = [];
        foreach ($intervals as $item) {
            $res = array_merge($res, $item instanceof Interval ? [$item] : $item->intervals);
        }

        return $res;
    }

    public function including(Time $time): self
    {
        return $this->filter(static fn (Interval $item): bool => $item->includes($time));
    }

    public function includes(Time $time): bool
    {
        return $this->exists(static fn (Interval $item): bool => $item->includes($time));
    }

    public function includesAll(Time $time): bool
    {
        return $this->forAll(static fn (Interval $item): bool => $item->includes($time));
    }

    public function containing(Interval $interval): self
    {
        return $this->filter(static fn (Interval $item): bool => $item->contains($interval));
    }

    public function contains(Interval $interval): bool
    {
        return $this->exists(static fn (Interval $item): bool => $item->contains($interval));
    }

    public function containsAll(Interval $interval): bool
    {
        return $this->forAll(static fn (Interval $item): bool => $item->contains($interval));
    }

    public function overlapping(Interval $interval): self
    {
        return $this->filter(static fn (Interval $item): bool => $item->overlaps($interval));
    }

    public function overlaps(Interval $interval): bool
    {
        return $this->exists(static fn (Interval $item): bool => $item->overlaps($interval));
    }

    public function overlapsAll(Interval $interval): bool
    {
        return $this->forAll(static fn (Interval $item): bool => $item->overlaps($interval));
    }

    public function abutting(Interval $interval): self
    {
        return $this->filter(static fn (Interval $item): bool => $item->abuts($interval));
    }

    public function abuts(Interval $interval): bool
    {
        return $this->exists(static fn (Interval $item): bool => $item->abuts($interval));
    }

    public function bordersAll(Interval $interval): bool
    {
        return $this->forAll(static fn (Interval $item): bool => $item->abuts($interval));
    }

    /**
     * @param callable(Interval, non-negative-int=): bool $callback
     */
    public function exists(callable $callback): bool
    {
        foreach ($this->intervals as $key => $interval) {
            if (true === $callback($interval, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(Interval, non-negative-int=): bool $callback
     */
    public function forAll(callable $callback): bool
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
     * @param callable(Interval, non-negative-int=): TValue $callback
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
     * @param callable(Interval, int): bool $callback
     */
    public function filter(callable $callback): self
    {
        $data = [];
        foreach ($this->intervals as $key => $interval) {
            if (true === $callback($interval, $key)) {
                $data[] = $interval;
            }
        }

        return $data === $this->intervals ? $this : new IntervalSet(...$data);
    }

    /**
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, Interval, int): TReduceReturnType $callback
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

    public function difference(self|Interval ...$others): self
    {
        if ([] === $this->intervals) {
            return $this;
        }

        $aSpans = array_map(self::linearize(...), $this->union()->intervals);
        $bSpans = array_map(self::linearize(...), (new self())->push(...$others)->union()->intervals);

        return new IntervalSet(...array_map(self::unlinearize(...), $this->linearSpanDifference($aSpans, $bSpans)));
    }

    public function union(): self
    {
        if ([] === $this->intervals) {
            return $this;
        }

        /** @var ?Time $midnight */
        static $midnight;
        $midnight ??= Time::midnight();

        $spans = [];
        foreach ($this->intervals as $interval) {
            if (!$interval->includes($midnight)) {
                $spans[] = self::linearize($interval);

                continue;
            }

            foreach ($interval->splitAt($midnight) as $splitInterval) {
                $spans[] = self::linearize($splitInterval);
            }
        }

        return new IntervalSet(...array_map(self::unlinearize(...), $this->mergeLinearSpan($spans)));
    }

    /**
     * @param list<LinearSpan> $spans
     *
     * @return list<LinearSpan>
     */
    private function mergeLinearSpan(array $spans): array
    {
        usort($spans, fn (array $x, array $y): int => $x[0] <=> $y[0]);

        $merged = [];

        foreach ($spans as [$start, $end]) {
            if ([] !== $merged) {
                $lastIndex = array_key_last($merged);
                [$prevStart, $prevEnd] = $merged[$lastIndex];

                if ($start <= $prevEnd) {
                    $merged[$lastIndex] = [$prevStart, max($prevEnd, $end)];
                    continue;
                }
            }

            $merged[] = [$start, $end];
        }

        if (count($merged) > 1) {
            $firstIndex = array_key_first($merged);
            $lastIndex = array_key_last($merged);

            [$firstStart, $firstEnd] = $merged[$firstIndex];
            [$lastStart, $lastEnd] = $merged[$lastIndex];

            if (0 === $firstStart && self::MICRO_PER_DAY === $lastEnd) {
                array_shift($merged);
                array_pop($merged);

                $merged[] = [$lastStart, $firstEnd];
            }
        }

        return $merged;
    }

    /**
     * @param list<LinearSpan> $aSpans
     * @param list<LinearSpan> $bSpans
     *
     * @return list<LinearSpan>
     */
    private function linearSpanDifference(array $aSpans, array $bSpans): array
    {
        $differences = [];
        foreach ($aSpans as [$aStart, $aEnd]) {
            $current = [[$aStart, $aEnd]];
            foreach ($bSpans as [$bStart, $bEnd]) {
                $next = [];
                foreach ($current as [$start, $end]) {
                    if ($bEnd <= $start || $bStart >= $end) {
                        $next[] = [$start, $end];
                        continue;
                    }

                    if ($bStart > $start) {
                        $next[] = [$start, min($bStart, $end)];
                    }

                    if ($bEnd < $end) {
                        $next[] = [max($bEnd, $start), $end];
                    }
                }

                $current = $next;
                if ([] === $current) {
                    break;
                }
            }

            foreach ($current as $span) {
                $differences[] = $span;
            }
        }

        return $differences;
    }

    /**
     * @return LinearSpan
     */
    private static function linearize(Interval $interval): array
    {
        $linearStart = $interval->start->toMicroOfDay();
        $linearEnd = $interval->end->toMicroOfDay();

        return $linearEnd < $linearStart
            ? [$linearStart, $linearEnd + self::MICRO_PER_DAY]
            : [$linearStart, $linearEnd];
    }

    /**
     * @param LinearSpan $linear
     */
    private static function unlinearize(array $linear): Interval
    {
        return Interval::between(Time::atMicroOfDay($linear[0]), Time::atMicroOfDay($linear[1]));
    }
}
