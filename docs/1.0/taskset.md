---
layout: default
title: TaskSet
---

# TaskSet

`Bakame\Tokei\TaskSet` extends `Bakame\Tokei\IntervalSet` by associating one or more identifiers while preserving all temporal behavior.

## Relationship with IntervalSet

`TaskSet` preserves all temporal behavior from `IntervalSet` while adding identification metadata.

| IntervalSet            | TaskSet                                |
|------------------------|----------------------------------------|
| Collection of Interval | Collection of Tasks                    |
| Temporal methods       | same method as `IntervalSet`           |
| Formatting             | same formatting rules as `IntervalSet` |


## Instantiation

```php
TaskSet::__construct(Task ...$items);
TaskSet::fromEvents(EventSet $items, Duration $duration, Bound $from = Bound::Start): self
TaskSet::fromIntervals(IntervalSet $intervals, Identifiers $identifiers): self
```

## Accessors

```php
TaskSet::totalDuration(): Duration
TaskSet::all(): array
TaskSet::isEmpty(): bool
TaskSet::indexOf(Task $task): ?int
TaskSet::lastIndexOf(Task $task): ?int
TaskSet::has(Task ...$items): bool
TaskSet::get(int $offset): Task
TaskSet::nth(int $offset): ?Task
TaskSet::first(): ?Task
TaskSet::last(): ?Task
```

## Formatting

```php
TaskSet::formatAll(IntervalFormat $format, ?Unit $unit = null): array
```
Supports the same formatting arguments as the `IntervalSet::formatAll()` method but enhances
the result by including the tasks identifiers using `Identifiers::toCommaSeparated` method results.

```php
$taskSet = new TaskSet(
    Task::for(Interval::since(Time::at(hour: 1), Duration::of(hours: 2)), 'early-morning'),
    Task::for(Interval::between(Time::at(hour: 9), Time::at(hour:12, minute: 30)), 'morning'),
    Task::for(Interval::between(Time::at(hour:19), Time::at(hour:23, minute: 30)), 'evening'),
);
$taskSet->formatAll(IntervalFormat::Iso80000, Unit::Hour)
// returns
// [
//     "[1,3);early-morning",
//     "[9,12.500000);morning",
//     "1[19,23.500000);evening"
// ]
```

## PHP Integration

The class implements PHP's `Countable`, `IteratorAggregate` and `Serializable` interfaces.
For JSON serialization, the class uses the same serialization as the `IntervalSet` class.
The method returns a list of serialized `Task` in their JSON representation.

```php
TaskSet::count(): int
TaskSet::getIterator(): iterable<Task>
TaskSet::jsonSerialize(): list<Task>
```

_`(TaskSet::jsonSerialize()` returns the same output as `TaskSet::all()` method._

```php

$taskSet = new TaskSet(
    Task::for(Interval::since(Time::at(hour: 1), Duration::of(hours: 2)), 'early-morning'),
    Task::for(Interval::between(Time::at(hour: 9), Time::at(hour:12, minute: 30)), 'morning'),
    Task::for(Interval::between(Time::at(hour:19), Time::at(hour:23, minute: 30)), 'evening'),
);
echo json_encode($taskSet, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
// returns
// [
//     "01:00:00/PT2H;early-morning",
//     "09:00:00/PT3H30M;morning",
//     "19:00:00/PT4H30M;evening"
// ]
```

## Temporal queries methods

```php
TaskSet::next(Time $atOrAfter, SearchMode $mode, Bound $using = Bound::Start): TaskSet
TaskSet::previous(Time $before, SearchMode $mode, Bound $using = Bound::Start): TaskSet
TaskSet::nearest(Time $around, Bound $using = Bound::Start): TaskSet
TaskSet::includes(Time $time): TaskSet
TaskSet::contains(Interval $interval): TaskSet
TaskSet::overlaps(Interval $interval): TaskSet
TaskSet::abuts(Interval $interval): TaskSet
```

## Temporal algebra methods

```php
TaskSet::union(Task|TaskSet ...$others): TaskSet 
TaskSet::complement(): TaskSet
TaskSet::intersect(Task|TaskSet ...$others): TaskSet
TaskSet::difference(Task|TaskSet ...$others): TaskSet
TaskSet::gaps(): TaskSet
```

## Temporal transformation methods

```php
TaskSet::sorted(Bound $by = Bound::Start, SortDirection $direction = SortDirection::Ascending): TaskSet
TaskSet::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): TaskSet
TaskSet::shift(Duration $duration): TaskSet
TaskSet::expand(Duration $duration): TaskSet
```

## Collection queries methods

```php
TaskSet::any(callable $callback): bool
TaskSet::every(callable $callback): bool
TaskSet::each(callable $callback): bool
TaskSet::reduce(callable $callback, mixed $initial = null): mixed
TaskSet::firstMatching(callable $callback): ?Task
TaskSet::lastMatching(callable $callback): ?Task
```

## Collection transformations methods

```php
TaskSet::map(callable $callback): iterable
TaskSet::transform(callable $callback): TaskSet
TaskSet::filter(callable $callback): TaskSet
```

## Collection builders methods

```php
TaskSet::append(Task|TaskSet ...$items): TaskSet
TaskSet::remove(int ...$offset): TaskSet
TaskSet::replace(int $offset, Interval $newInterval): TaskSet
```