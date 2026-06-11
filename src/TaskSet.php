<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Traversable;

use function array_column;
use function array_merge;
use function array_values;
use function count;
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

    public function __construct(Task|TaskSet ...$items)
    {
        $this->items = self::sortChronologically($items);
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
     * @param array<Task|TaskSet> $items
     *
     * @return list<Task>
     */
    private static function sortChronologically(array $items): array
    {
        $res = [];
        foreach ($items as $task) {
            if ($task instanceof TaskSet) {
                $res = array_merge($res, $task->items);
                continue;
            }

            $res[] = $task;
        }

        usort(
            $res,
            static fn (Task $a, Task $b): int =>
            0 !== ($cmp = $a->period->start->compareTo($b->period->start))
                ? $cmp
                : $a->period->duration->compareTo($b->period->duration)
        );

        return $res;
    }

    public function duration(): Duration
    {
        return $this->toIntervalSet()->duration();
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
     * @param callable(Task, int): Task $callback
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

    public function toIntervalSet(): IntervalSet
    {
        return new IntervalSet(...array_column($this->items, 'period'));
    }

    public function abuts(Interval|Task $interval): self
    {
        return $this->filter(fn (Task $task): bool => $task->period->abuts($interval));
    }

    public function overlaps(Interval|Task $interval): self
    {
        return $this->filter(fn (Task $task): bool => $task->period->overlaps($interval));
    }

    public function includes(Time|Event $time): self
    {
        return $this->filter(fn (Task $task): bool => $task->period->includes($time));
    }

    public function gaps(): self
    {
        return new self(
            ...$this
                ->toIntervalSet()
                ->gaps()
                ->map(fn (Interval $interval): Task => Task::for($interval))
        );
    }

    /**
     * @param iterable<TaskSet|Task> $sets
     *
     * @throws InvalidDuration|InvalidInterval|TemporalException
     */
    public function intersect(iterable $sets): self
    {
        $others = new self(...$sets);

        return $others->isEmpty() ? new self() : (new self(
            ...$this
                ->toIntervalSet()
                ->intersect($others)
                ->union()
                ->transform(
                    fn (Interval $interval): IntervalSet => $interval
                        ->splitAt(
                            ...$this
                                ->toIntervalSet()
                                ->push($others)
                                ->atomicBoundaries()
                        )
                )
                ->map(
                    function (Interval $interval) use ($others): self {
                        $results = [];
                        foreach ($this->overlaps($interval) as $aTask) {
                            foreach ($others->overlaps($interval) as $bTask) {
                                $intersection = $aTask->period->intersect($bTask->period)?->intersect($interval);
                                if (null !== $intersection) {
                                    $results[] = Task::for($intersection, Identifiers::merge([$aTask, $bTask]));
                                }
                            }
                        }

                        return new self(...$results);
                    }
                )
        ))->filter(fn (Task $task): bool => !$task->identifiers->isEmpty());
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
            ...$set
                ->toIntervalSet()
                ->atomic()
                ->map(fn (Interval $interval): Task => Task::for($interval, self::computeIdentifiers($set, $interval)))
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
            ...$this
                ->toIntervalSet()
                ->difference($others)
                ->atomic()
                ->map(fn (Interval $interval): Task => Task::for($interval, self::computeIdentifiers($this, $interval)))
        );
    }

    private static function computeIdentifiers(self $source, Interval $interval): Identifiers
    {
        return $source
            ->overlaps($interval)
            ->reduce(
                static fn (Identifiers $carry, Task $task): Identifiers => Identifiers::merge([$carry, $task]),
                new Identifiers()
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
