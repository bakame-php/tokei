<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeInterface;
use Traversable;

use function array_map;
use function array_values;
use function count;
use function in_array;
use function usort;

/**
 * @implements TemporalSet<Task>
 */
final class TaskSet implements TemporalSet
{
    /** @var list<Task> */
    private readonly array $items;
    /** @var array<non-empty-string, TemporalSearch<Task>> */
    private array $engine;

    public function __construct(Task|NativeTask|TaskSet ...$items)
    {
        $this->items = self::sortChronologically($items);
    }

    public static function fromEvents(EventSet $items, Duration|DateInterval $duration, Bound $from = Bound::Start): self
    {
        return new self(...$items->map(fn (Event $event): Task => Task::fromEvent($event, $duration, $from)));
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifiers
     *
     * @throws TemporalException
     */
    public static function fromIntervals(IntervalSet $intervals, Identifiers|HasIdentifiers|string $identifiers): self
    {
        return new self(...$intervals->map(fn (Interval $interval): Task => Task::for($interval, $identifiers)));
    }

    /**
     * @return TemporalSearch<Task>
     */
    private function engine(Bound $using = Bound::Start): TemporalSearch
    {
        if (!isset($this->engine[$using->name])) {
            /** @var TemporalSearch<Task> $engine */
            $engine = TemporalSearch::forIntervals($this, $using);

            $this->engine[$using->name] = $engine;
        }

        return $this->engine[$using->name];
    }

    /**
     * @param array<Task|TaskSet|NativeTask> $items
     *
     * @return list<Task>
     */
    private static function sortChronologically(array $items): array
    {
        $res = [];
        foreach ($items as $task) {
            if ($task instanceof TaskSet) {
                $res = [...$res, ...$task->items];
                continue;
            }

            if ($task instanceof NativeTask) {
                $res[] = Task::fromNative($task);
                continue;
            }

            $res[] = $task;
        }

        usort(
            $res,
            static fn (Task $a, Task $b): int =>
            0 !== ($cmp = $a->interval->start->compareTo($b->interval->start))
                ? $cmp
                : $a->interval->duration->compareTo($b->interval->duration)
        );

        return $res;
    }

    /**
     * @throws InvalidTime
     *
     * @return list<non-empty-string>
     */
    public function formatAll(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): array
    {
        return array_map(static fn (Task $item): string => $item->format($format, $unit), $this->items);
    }

    /**
     * @return list<NativeTask>
     */
    public function toNative(DateTimeInterface $reference): array
    {
        return array_map(static fn (Task $item): NativeTask => $item->toNative($reference), $this->items);
    }

    public function duration(): Duration
    {
        return IntervalSet::chronological($this)->duration();
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
     * @return list<Task>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @return list<Task>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function indexOf(Task $task): ?int
    {
        return array_find_key($this->items, fn (Task $item) => $task->equals($item));
    }

    public function lastIndexOf(Task $task): ?int
    {
        for ($offset = count($this->items) - 1; $offset >= 0; --$offset) {
            if ($task->equals($this->items[$offset])) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * Tells whether the given interval is present in the set.
     */
    public function has(Task ...$items): bool
    {
        $check = new self(...$items);

        return !$check->isEmpty()
            && $check->every(fn (Task $item): bool => null !== $this->indexOf($item));
    }

    /**
     * @throws TokeiException If the offset is out of range.
     */
    public function get(int $offset): Task
    {
        return $this->nth($offset) ?? throw TokeiException::dueToInvalidOffset($offset, self::class);
    }

    /**
     * Returns the interval at the given position, or null if it does not exist.
     *
     * Supports negative offsets, where -1 refers to the last task.
     */
    public function nth(int $offset): ?Task
    {
        $count = count($this->items);
        if ($offset < 0) {
            $offset = $count + $offset;
        }

        return $this->items[$offset] ?? null;
    }

    public function first(): ?Task
    {
        return $this->nth(0);
    }

    public function last(): ?Task
    {
        return $this->nth(-1);
    }

    /**
     * @param callable(Task, int=): bool $predicate
     */
    public function firstMatching(callable $predicate): ?Task
    {
        return $this->engine()->firstMatching($predicate);
    }

    /**
     * @param callable(Task, int=): bool $predicate
     */
    public function lastMatching(callable $predicate): ?Task
    {
        return $this->engine()->lastMatching($predicate);
    }

    /**
     * @param callable(Task, int=): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        return $this->engine()->any($predicate);
    }

    /**
     * @param callable(Task, int=): bool $predicate
     */
    public function every(callable $predicate): bool
    {
        return $this->engine()->every($predicate);
    }

    public function next(Time|Event|NativeEvent|DateTimeInterface $atOrAfter, SearchMode $mode, Bound $using = Bound::Start): self
    {
        return new self(...$this->engine($using)->next($atOrAfter, $mode));
    }

    public function previous(Time|Event|NativeEvent|DateTimeInterface $before, SearchMode $mode, Bound $using = Bound::Start): self
    {
        return new self(...$this->engine($using)->previous($before, $mode));
    }

    public function nearest(Time|Event|NativeEvent|DateTimeInterface $around, Bound $using = Bound::Start): self
    {
        return new self(...$this->engine($using)->nearest($around));
    }

    /**
     * @template TValue
     *
     * @param callable(Task, int): TValue $callback
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
     * @param callable(Task, int): (Task|TaskSet) $callback
     */
    public function transform(callable $callback): self
    {
        return new self(...$this->map($callback));
    }

    /**
     * @param callable(Task, int): bool $callback
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
     * @param callable(TReduceInitial|TReduceReturnType, Task, int): TReduceReturnType $callback
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

    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        return $this->transform(static fn (Task $task): Task => $task->during($task->interval->roundTo($unit, $mode)));
    }

    public function roundDurationTo(Unit $unit, SnapMode $mode = SnapMode::Nearest, Bound $anchor = Bound::Start): self
    {
        return $this->transform(static fn (Task $task): Task => $task->during($task->interval->roundDurationTo($unit, $mode, $anchor)));
    }

    /**
     * Iterates over all tasks in this set.
     *
     * The callback receives the current Task and its index.
     * If the callback returns false, iteration stops immediately.
     *
     * @param callable(Task, int): mixed $callback
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

    public function push(Task|TaskSet|Interval|IntervalSet ...$tasks): self
    {
        return [] === $tasks ? $this : new self(...$this->items, ...self::filterTasks(...$tasks));
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

        return $this->filter(static fn (Task $item, int $index): bool => !in_array($index, $normalized, true));
    }

    /**
     * @throws InvalidDuration
     * @throws TimeException
     */
    public function replace(int $offset, Task $item): self
    {
        if ($offset < 0) {
            $offset += count($this->items);
        }

        isset($this->items[$offset]) || throw TimeException::dueToInvalidOffset($offset, self::class);

        $intervals = $this->items;
        $intervals[$offset] = $item;

        return new self(...$intervals);
    }

    public function abuts(Interval|Task|NativeInterval|NativeTask $interval): self
    {
        return $this->filter(fn (Task $task): bool => $task->interval->abuts($interval));
    }

    public function overlaps(Interval|Task|NativeInterval|NativeTask $interval): self
    {
        return $this->filter(fn (Task $task): bool => $task->interval->overlaps($interval));
    }

    public function contains(Interval|Task|NativeInterval|NativeTask $interval): self
    {
        return $this->filter(fn (Task $task): bool => $task->interval->contains($interval));
    }

    public function includes(Time|Event|NativeEvent|DateTimeInterface $time): self
    {
        return $this->filter(fn (Task $task): bool => $task->interval->includes($time));
    }

    public function outsideOf(Time|Event|NativeEvent|DateTimeInterface $time): self
    {
        return $this->filter(fn (Task $task): bool => !$task->interval->includes($time));
    }

    public function gaps(): self
    {
        return new self(
            ...IntervalSet::chronological($this)
                ->gaps()
                ->map(fn (Interval $interval): Task => Task::for($interval))
        );
    }

    /**
     * @param iterable<TaskSet|Task|NativeTask> $sets
     *
     * @throws InvalidDuration|InvalidInterval|TemporalException
     */
    public function intersect(iterable $sets): self
    {
        $splitTasks = static fn (TaskSet $set): TaskSet => $set
            ->transform(
                static fn (Task $task, int $offset): TaskSet|Task =>
                    IntervalType::Overflow !== $task->interval->type
                        ? $task
                        : TaskSet::fromIntervals($task->interval->splitAt(Time::midnight()), $task)
            );

        /**
         * @param 'A'|'B' $source
         *
         * @return list<array{event: Event, type: Bound, source: 'A'|'B'}>
         */
        $addEvents = static fn (TaskSet $set, string $source): array => $splitTasks($set)
            ->reduce(
                function (array $events, Task $task) use ($source): array {
                    $events[] = ['event' => Event::fromTask($task, Bound::Start), 'type' => Bound::Start, 'source' => $source];
                    $events[] = ['event' => Event::fromTask($task, Bound::End), 'type' => Bound::End, 'source' => $source];

                    return $events;
                },
                []
            );

        /**
         * @param array{event: Event, type: Bound, source: 'A'|'B'} $a
         * @param array{event: Event, type: Bound, source: 'A'|'B'} $b
         *
         * @return int<-1, 1>
         */
        $sortEvents = static function (array $a, array $b): int {
            /* @phpstan-ignore-next-line */
            $cmp = $a['event']->at->compareTo($b['event']);

            /* @phpstan-ignore-next-line */
            return 0 !== $cmp ? $cmp : (Bound::End === $a['type'] ? -1 : 1);
        };

        /**
         * @throws InvalidDuration|TemporalException
         */
        $flush = static fn (?Time $from, Time $to, Identifiers $labelsA, Identifiers $labelsB): ?Task =>
            (null === $from || $from->equals($to) || $labelsA->isEmpty() || $labelsB->isEmpty())
                ? null
                : Task::for(Interval::between($from, $to), $labelsA->merge($labelsB));

        $others = new self(...$sets);
        if ($this->isEmpty() || $others->isEmpty()) {
            return new self();
        }

        /** @var list<array{event: Event, type: Bound, source: non-empty-string}> $events */
        $events = [...$addEvents($this, 'A'), ...$addEvents($others, 'B')];
        usort($events, $sortEvents);

        $activeA = new Identifiers();
        $activeB = new Identifiers();
        $lastTime = null;
        $result = [];
        foreach ($events as $event) {
            $currentTime = $event['event']->at;
            $task = $flush($lastTime, $currentTime, $activeA, $activeB);
            if (null !== $task) {
                $result[] = $task;
            }

            if ('A' === $event['source']) {
                $activeA = Bound::Start === $event['type']
                    ? $activeA->merge($event['event']->identifiers)
                    : new Identifiers();
            }

            if ('B' === $event['source']) {
                $activeB = Bound::Start === $event['type']
                    ? $activeB->merge($event['event']->identifiers)
                    : new Identifiers();
            }

            $lastTime = $currentTime;
        }

        return new self(...$result);
    }

    /**
     * @param iterable<TaskSet|Task|Interval|IntervalSet> $sets
     *
     * @throws InvalidDuration|InvalidInterval|TemporalException
     */
    public function union(iterable $sets = []): self
    {
        $set = $this->push(...$sets);

        return new self(
            ...IntervalSet::chronological($set)
                ->atomic()
                ->map(fn (Interval $interval): Task => Task::for($interval, new Identifiers($set->overlaps($interval))))
        );
    }

    /**
     * @param iterable<TaskSet|Task|Interval|IntervalSet> $sets
     *
     * @throws InvalidDuration|InvalidInterval|TemporalException
     */
    public function difference(iterable $sets): self
    {
        $others = new self(...self::filterTasks(...$sets));

        return $others->isEmpty() ? $this : new self(
            ...IntervalSet::chronological($this)
                ->difference($others)
                ->atomic()
                ->map(fn (Interval $interval): Task => Task::for($interval, new Identifiers($this->overlaps($interval))))
        );
    }

    /**
     * @throws TemporalException
     *
     * @return list<Task>
     */
    private static function filterTasks(Task|IntervalSet|TaskSet|Interval ...$tasks): array
    {
        $taskList = [];
        foreach ($tasks as $task) {
            $taskList = [...$taskList, ...match (true) {
                $task instanceof Task => [$task],
                $task instanceof Interval => [Task::for($task)],
                $task instanceof IntervalSet => $task->map(static fn (Interval $t): Task => Task::for($t)),
                default => $task,
            }];
        }

        return array_values($taskList);
    }

    /**
     * @return array{0: array{tasks: list<Task>}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['tasks' => $this->items], []];
    }

    /**
     * @param array{0: array{tasks: list<Task>}, 1: array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->items = $properties['tasks'];
    }
}
