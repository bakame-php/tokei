<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Traversable;

use function array_map;
use function array_merge;
use function count;
use function usort;

/**
 * @implements TemporalSet<Event>
 */
final class EventSet implements TemporalSet
{
    /** @var list<Event> */
    private readonly array $items;
    /** @var TemporalSearch<Event> */
    private TemporalSearch $engine;

    public function __construct(Event|EventSet ...$items)
    {
        $this->items = self::sortChronologically($items);
    }

    /**
     * @return TemporalSearch<Event>
     */
    private function engine(): TemporalSearch
    {
        if (!isset($this->engine)) {
            /** @var TemporalSearch<Event> $engine */
            $engine = TemporalSearch::forTimes($this);

            $this->engine = $engine;
        }

        return $this->engine;
    }

    /**
     * @param array<EventSet|Event> $items
     *
     * @return list<Event>
     */
    private static function sortChronologically(array $items): array
    {
        $res = [];
        foreach ($items as $item) {
            if ($item instanceof EventSet) {
                $res = array_merge($res, $item->items);
                continue;
            }

            $res[] = $item;
        }

        usort($res, static fn (Event $a, Event $b): int => $a->at->compareTo($b->at));

        return $res;
    }

    /**
     * @throws InvalidTime
     *
     * @return list<non-empty-string>
     */
    public function allFormatted(TimeFormat $format = TimeFormat::Iso8601): array
    {
        return array_map(static fn (Event $item): string => $item->format($format), $this->items);
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
     * @return list<Event>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @return list<Event>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * @throws TokeiException If the offset is out of range.
     */
    public function get(int $offset): Event
    {
        return $this->nth($offset) ?? throw TokeiException::dueToInvalidOffset($offset, self::class);
    }

    /**
     * Returns the interval at the given position, or null if it does not exist.
     *
     * Supports negative offsets, where -1 refers to the last task.
     */
    public function nth(int $offset): ?Event
    {
        $count = count($this->items);
        if ($offset < 0) {
            $offset = $count + $offset;
        }

        return $this->items[$offset] ?? null;
    }

    public function first(): ?Event
    {
        return $this->nth(0);
    }

    public function last(): ?Event
    {
        return $this->nth(-1);
    }

    public function indexOf(Event $event): ?int
    {
        foreach ($this->items as $offset => $item) {
            if ($event->equals($item)) {
                return $offset;
            }
        }

        return null;
    }

    public function lastIndexOf(Event $event): ?int
    {
        for ($offset = count($this->items) - 1; $offset >= 0; --$offset) {
            if ($event->equals($this->items[$offset])) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param callable(Event, int=): bool $predicate
     */
    public function firstMatching(callable $predicate): ?Event
    {
        return $this->engine()->firstMatching($predicate);
    }

    /**
     * @param callable(Event, int=): bool $predicate
     */
    public function lastMatching(callable $predicate): ?Event
    {
        return $this->engine()->lastMatching($predicate);
    }

    /**
     * @param callable(Event, int=): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        return $this->engine()->any($predicate);
    }

    /**
     * @param callable(Event, int=): bool $predicate
     */
    public function every(callable $predicate): bool
    {
        return $this->engine()->every($predicate);
    }

    /**
     * @template TValue
     *
     * @param callable(Event, int): TValue $callback
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
     * @param callable(Event, int): (Event|EventSet) $callback
     */
    public function transform(callable $callback): self
    {
        return new self(...$this->map($callback));
    }

    /**
     * @param callable(Event, int): bool $callback
     */
    public function filter(callable $callback): self
    {
        $data = [];
        foreach ($this->items as $offset => $item) {
            if (true === $callback($item, $offset)) {
                $data[] = $item;
            }
        }

        return new self(...$data);
    }

    /**
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, Event, int): TReduceReturnType $callback
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
     * Iterates over all events in this set.
     *
     * The callback receives the current Task and its index.
     * If the callback returns false, iteration stops immediately.
     *
     * @param callable(Event, int): mixed $callback
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

    public function push(Event|EventSet ...$items): self
    {
        if ([] === $items) {
            return $this;
        }

        $itemList = [];
        foreach ($items as $item) {
            if ($item instanceof self) {
                $itemList = [...$itemList, ...$item];
                continue;
            }
            $itemList[] = $item;
        }

        return new self(...$this->items, ...$itemList);
    }

    public function inside(Interval|Task $interval): self
    {
        if ($interval instanceof Task) {
            $interval = $interval->period;
        }

        return $this->filter(fn (Event $event): bool => $interval->includes($event));
    }

    public function at(Time|Event $time): self
    {
        return $this->filter(fn (Event $event): bool => $event->at->equals($time));
    }

    public function next(Time|Event $atOrAfter, SearchMode $mode): self
    {
        /** @var TemporalSearch<Event> $navigator */
        $navigator = TemporalSearch::forTimes($this);

        return new self(...$navigator->next($atOrAfter, $mode));
    }

    public function previous(Time|Event $before, SearchMode $mode): self
    {
        /** @var TemporalSearch<Event> $navigator */
        $navigator = TemporalSearch::forTimes($this);

        return new self(...$navigator->previous($before, $mode));
    }

    public function nearest(Time|Event $around): self
    {
        /** @var TemporalSearch<Event> $navigator */
        $navigator = TemporalSearch::forTimes($this);

        return new self(...$navigator->nearest($around));
    }

    /**
     * @return array{0: array{events: list<Event>}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['events' => $this->items], []];
    }

    /**
     * @param array{0: array{events: list<Event>}, 1: array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->items = $properties['events'];
    }
}
