<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeInterface;
use Traversable;

use function array_diff_key;
use function array_key_exists;
use function array_map;
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

    public function __construct(Event|NativeEvent|EventSet ...$items)
    {
        $this->items = self::sortChronologically($items);
    }

    public static function fromTasks(TaskSet $tasks, Bound $anchor = Bound::Start): self
    {
        return new self(...$tasks->map(fn (Task $task): Event => Event::fromTask($task, $anchor)));
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
     * @param array<EventSet|Event|NativeEvent> $items
     *
     * @return list<Event>
     */
    private static function sortChronologically(array $items): array
    {
        $res = [];
        foreach ($items as $item) {
            if ($item instanceof EventSet) {
                $res = [...$res, ...$item->items];
                continue;
            }

            $res[] = $item instanceof NativeEvent ? Event::fromNative($item) : $item;
        }

        usort($res, Time::compare(...));

        return $res;
    }

    /**
     * @throws InvalidTime
     *
     * @return list<non-empty-string>
     */
    public function formatAll(TimeFormat $format = TimeFormat::Iso8601Extended): array
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
        return array_find_key($this->items, fn (Event $item): bool => $event->equals($item));
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
     * Tells whether the given interval is present in the set.
     */
    public function has(Event ...$items): bool
    {
        $check = new self(...$items);

        return !$check->isEmpty()
            && $check->every(fn (Event $item): bool => null !== $this->indexOf($item));
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

    public function push(Event|EventSet|NativeEvent ...$items): self
    {
        if ([] === $items) {
            return $this;
        }

        $itemList = [];
        foreach ($items as $item) {
            if ($item instanceof EventSet) {
                $itemList = [...$itemList, ...$item];
                continue;
            }
            $itemList[] = $item;
        }

        return new self(...$this->items, ...$itemList);
    }

    public function gaps(): IntervalSet
    {
        $nbItems = count($this->items);
        if ($nbItems < 2) {
            return new IntervalSet();
        }

        $gaps = [];
        for ($i = 0; $i < $nbItems - 1; $i++) {
            $gaps[] = Interval::between($this->items[$i], $this->items[$i + 1]);
        }

        return new IntervalSet(...$gaps);
    }

    public function union(Event|EventSet|NativeEvent ...$items): self
    {
        if ([] === $items) {
            return $this;
        }

        $others = new self(...$items);
        if ($others->isEmpty()) {
            return $this;
        }

        $current = self::atomic($this);
        foreach (self::atomic($others) as $offset => $other) {
            $current[$offset] = !array_key_exists($offset, $current)
                ? $other
                : $current[$offset]->named($current[$offset]->identifiers->merge($other->identifiers));
        }

        return new self(...$current);
    }

    public function intersect(Event|EventSet|NativeEvent ...$items): self
    {
        if ([] === $items) {
            return new self();
        }

        $others = new self(...$items);
        if ($others->isEmpty()) {
            return new self();
        }

        $current = self::atomic($this);
        $result = [];
        foreach (self::atomic($others) as $offset => $item) {
            if (array_key_exists($offset, $current)) {
                $result[$offset] = $current[$offset]->named($current[$offset]->identifiers->merge($item->identifiers));
            }
        }

        return new self(...$result);
    }

    public function difference(Event|EventSet|NativeEvent ...$items): self
    {
        if ([] === $items) {
            return $this;
        }

        $others = new self(...$items);

        return $others->isEmpty()
            ? $this
            : new self(...array_diff_key(self::atomic($this), self::atomic($others)));
    }

    /**
     * @throws TemporalException
     *
     * @return array<non-empty-string, Event>
     */
    private static function atomic(self $set): array
    {
        $result = [];
        foreach ($set->items as $item) {
            $offset = $item->at->format();
            $result[$offset] = ([] === $result || !array_key_exists($offset, $result))
                ? $item
                : $result[$offset]->named($item->identifiers->merge($item->identifiers));
        }

        return $result;
    }

    public function inside(Interval|Task|NativeTask|NativeInterval $interval): self
    {
        $interval = InputNormalizer::interval($interval);

        return $this->filter(fn (Event $event): bool => $interval->includes($event));
    }

    public function outside(Interval|Task|NativeTask|NativeInterval $interval): self
    {
        $interval = InputNormalizer::interval($interval);

        return $this->filter(fn (Event $event): bool => !$interval->includes($event));
    }

    public function at(Time|Event|NativeEvent|DateTimeInterface $time): self
    {
        return $this->filter(fn (Event $event): bool => $event->at->equals($time));
    }

    public function before(Time|Event|NativeEvent|DateTimeInterface $time): self
    {
        return $this->filter(fn (Event $event): bool => $event->at->isBefore($time));
    }

    public function after(Time|Event|NativeEvent|DateTimeInterface  $time): self
    {
        return $this->filter(fn (Event $event): bool => $event->at->isAfter($time));
    }

    public function next(Time|Event|NativeEvent|DateTimeInterface $atOrAfter, SearchMode $mode): self
    {
        return new self(...$this->engine()->next($atOrAfter, $mode));
    }

    public function previous(Time|Event|NativeEvent|DateTimeInterface $before, SearchMode $mode): self
    {
        return new self(...$this->engine()->previous($before, $mode));
    }

    public function nearest(Time|Event|NativeEvent|DateTimeInterface $around): self
    {
        return new self(...$this->engine()->nearest($around));
    }

    public function shift(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): self
    {
        $duration = InputNormalizer::duration($duration);

        return $duration->isZero()
            ? $this
            : $this->transform(fn (Event $event): Event => $event->occursOn($event->at->shift($duration)));
    }

    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        return $this->transform(static fn (Event $event): Event => $event->occursOn($event->at->roundTo($unit, $mode)));
    }

    /**
     * @return list<NativeEvent>
     */
    public function toNative(DateTimeInterface $reference): array
    {
        return array_map(fn (Event $event): NativeEvent => $event->toNative($reference), $this->items);
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
