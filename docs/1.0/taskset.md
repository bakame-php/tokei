---
layout: default
title: TaskSet
---

# TaskSet

`Bakame\Tokei\TaskSet` extends `Bakame\Tokei\IntervalSet` by associating one or more identifiers while preserving all temporal behavior.

## Relationship with Interval

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
TaskSet::duration(): Duration
TaskSet::count(): int
TaskSet::getIterator(): Traversable
TaskSet::jsonSerialize(): array
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
TaskSet::formatAll(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): array
```

## Interacting with PHP's native Date API

```php
TaskSet::toNative(DateTimeInterface $reference): array
```

## Temporal selection methods

```php
TaskSet::next(Time $atOrAfter, SearchMode $mode, Bound $using = Bound::Start): self
TaskSet::previous(Time $before, SearchMode $mode, Bound $using = Bound::Start): self
TaskSet::nearest(Time $around, Bound $using = Bound::Start): self
TaskSet::abuts(Interval $interval): self
TaskSet::overlaps(Interval $interval): self
TaskSet::contains(Interval $interval): self
TaskSet::includes(Time $time): self
TaskSet::outsideOf(Time $time): self
```

## Temporal algebra methods

```php
TaskSet::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
TaskSet::roundDurationTo(Unit $unit, SnapMode $mode = SnapMode::Nearest, Bound $anchor = Bound::Start): self
TaskSet::gaps(): self
TaskSet::intersect(iterable $sets): self
TaskSet::union(iterable $sets = []): self
TaskSet::difference(iterable $sets): self
TaskSet::shift(Duration $duration): self
```

## Collection methods

```php
TaskSet::getIterator(): Traversable
TaskSet::jsonSerialize(): array
TaskSet::all(): array
TaskSet::isEmpty(): bool
TaskSet::firstMatching(callable $predicate): ?Task
TaskSet::lastMatching(callable $predicate): ?Task
TaskSet::any(callable $predicate): bool
TaskSet::every(callable $predicate): bool
TaskSet::map(callable $callback): iterable
TaskSet::transform(callable $callback): self
TaskSet::filter(callable $callback): self
TaskSet::reduce(callable $callback, mixed $initial = null): mixed
TaskSet::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
TaskSet::roundDurationTo(Unit $unit, SnapMode $mode = SnapMode::Nearest, Bound $anchor = Bound::Start): self
TaskSet::each(callable $callback): bool
TaskSet::push(Task ...$tasks): self
TaskSet::remove(int ...$offsets): self
TaskSet::replace(int $offset, Task $item): self
```