<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Closure;
use DateTimeInterface;

use function count;

/**
 * This is a strategy-driven temporal query engine.
 *
 * @internal
 * @template TItem
 */
final readonly class TemporalSearch
{
    private const int CYCLE = 86_400_000_000;

    /**
     * @param TemporalSet<TItem> $items
     * @param Closure(TItem): Time $resolver
     */
    private function __construct(
        private TemporalSet $items,
        private Closure $resolver,
    ) {
    }

    /**
     * @param TemporalSet<Time|Event> $items
     *
     * @return self<Time|Event>
     */
    public static function forTimes(TemporalSet $items): self
    {
        return new self($items, self::extractTime(...));
    }

    /**
     * @param TemporalSet<Interval|Task> $items
     *
     * @return self<Interval|Task>
     */
    public static function forIntervals(TemporalSet $items, Bound $using): self
    {
        $extractInterval = fn (Interval|Task $item): Interval => $item instanceof Task ? $item->interval : $item;

        return new self(
            $items,
            static fn (Task|Interval $item): Time => Bound::Start === $using
                ? $extractInterval($item)->start
                : $extractInterval($item)->end
        );
    }

    private static function extractTime(Time|Event|NativeEvent|DateTimeInterface $time): Time
    {
        return match (true) {
            $time instanceof Event => $time->at,
            $time instanceof NativeEvent => Event::fromNative($time)->at,
            $time instanceof DateTimeInterface => Time::fromDateTime($time),
            default => $time,
        };
    }

    /**
     * @return iterable<non-negative-int, TItem>
     */
    public function next(Time|Event|NativeEvent|DateTimeInterface $atOrAfter, SearchMode $mode): iterable
    {
        return SearchMode::Linear === $mode
            ? $this->forwardSearch(fn ($item): bool => ($this->resolver)($item)->isAfterOrEqual($atOrAfter))
            : $this->circularSearch(
                (int) self::extractTime($atOrAfter)->toOffset(Unit::Microsecond),
                static function (int $at, int $reference): int {
                    $delta = $at - $reference;
                    if ($delta <= 0) {
                        $delta += self::CYCLE;
                    }

                    return $delta;
                }
            );
    }

    /**
     * @return iterable<non-negative-int, TItem>
     */
    public function previous(Time|Event|NativeEvent|DateTimeInterface $before, SearchMode $mode): iterable
    {
        return SearchMode::Linear === $mode
            ? $this->forwardSearch(fn ($item): bool => ($this->resolver)($item)->isBefore($before))
            : $this->circularSearch(
                (int) self::extractTime($before)->toOffset(Unit::Microsecond),
                static function (int $at, int $reference): int {
                    $delta = $reference - $at;
                    if ($delta < 0) {
                        $delta += self::CYCLE;
                    }

                    return $delta;
                }
            );
    }

    /**
     * @return iterable<non-negative-int, TItem>
     */
    public function nearest(Time|Event|NativeEvent|DateTimeInterface $around): iterable
    {
        return $this->circularSearch(
            (int) self::extractTime($around)->toOffset(Unit::Microsecond),
            static function (int $at, int $reference): int {
                $calculate = static fn (int $value): int => ($value + self::CYCLE) % self::CYCLE;

                return min($calculate($at - $reference), $calculate($reference - $at));
            }
        );
    }

    /**
     * @param Closure(TItem, int): bool $callback
     *
     * @return iterable<non-negative-int, TItem>
     */
    private function forwardSearch(Closure $callback): iterable
    {
        foreach ($this->items as $offset => $item) {
            if (true === $callback($item, $offset)) {
                yield $offset => $item;
            }
        }
    }

    /**
     * @param Closure(int, int): int $metrics
     *
     * @return iterable<non-negative-int, TItem>
     */
    private function circularSearch(int $current, Closure $metrics): iterable
    {
        $bestDelta = null;
        $results = [];
        foreach ($this->items as $offset => $item) {
            $delta = $metrics((int) ($this->resolver)($item)->toOffset(Unit::Microsecond), $current);
            if (null === $bestDelta || $delta < $bestDelta) {
                $bestDelta = $delta;
                $results = [$offset => $item];
                continue;
            }

            if ($delta === $bestDelta) {
                $results[$offset] = $item;
            }
        }

        return $results;
    }

    /**
     * @param callable(TItem, int=): bool $predicate
     *
     * @return ?TItem
     */
    public function firstMatching(callable $predicate): mixed
    {
        foreach ($this->items as $offset => $item) {
            if (true === $predicate($item, $offset)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param callable(TItem, int=): bool $predicate
     *
     * @return ?TItem
     */
    public function lastMatching(callable $predicate): mixed
    {
        $items = $this->items->all();
        for ($offset = count($this->items) - 1; $offset >= 0; --$offset) {
            $item = $items[$offset];
            if (true === $predicate($item, $offset)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param callable(TItem, int=): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->items as $offset => $item) {
            if (true === $predicate($item, $offset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(TItem, int=): bool $predicate
     */
    public function every(callable $predicate): bool
    {
        foreach ($this->items as $offset => $item) {
            if (true !== $predicate($item, $offset)) {
                return false;
            }
        }

        return true;
    }
}
