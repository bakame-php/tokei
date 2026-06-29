---
layout: default
title: EventSet
---

# EventSet

`Bakame\Tokei\EventSet` associates one or more identifiers to `Time` instances.

## Instantiation

```php
EventSet::__construct(Event ...$items)
EventSet::fromTasks(TaskSet $tasks, Bound $anchor = Bound::Start): self
````

## Accessors

```php
EventSet::formatAll(TimeFormat $format = TimeFormat::Iso8601): array
EventSet::count(): int
EventSet::getIterator(): Traversable
EventSet::jsonSerialize(): array
EventSet::all(): array
EventSet::isEmpty(): bool
EventSet::get(int $offset): Event
EventSet::nth(int $offset): ?Event
EventSet::first(): ?Event
EventSet::last(): ?Event
EventSet::indexOf(Event $event): ?int
EventSet::lastIndexOf(Event $event): ?int
EventSet::has(Event ...$items): bool
```

`nth` and `get` supports negative index but differ on failure:

- `nth` returns `null` on invalid offset;
- `get` throws a `TimeException` exception on invalid offset;

## Formatting

Supports the same formatting arguments as the `Time::format` method.

```php
EventSet::formatAll(TimeFormat $format = TimeFormat::Iso8601): array
````

## Interacting with PHP's native Date API

```php
EventSet::toNative(DateTimeInterface $reference): array
```

Returns the list of `NativeEvent` instances converted using their `Event::toNative` method.

## Temporal selection methods

```php
EventSet::inside(Interval $interval): self
EventSet::outside(Interval $interval): self
EventSet::at(Time $time): self
EventSet::before(Time $time): self
EventSet::after(Time $time): self
EventSet::next(Time $atOrAfter, SearchMode $mode): self
EventSet::previous(Time $before, SearchMode $mode): self
EventSet::nearest(Time $around): self
EventSet::shift(Duration $duration): self
```

## Temporal algebra methods

```php
EventSet::gaps(): IntervalSet
EventSet::union(Event ...$items): self
EventSet::intersect(Event ...$items): self
EventSet::difference(Event ...$items): self
EventSet::shift(Duration $duration): self
EventSet::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
```

## Collection methods

```php
EventSet::firstMatching(callable $predicate): ?Event
EventSet::lastMatching(callable $predicate): ?Event
EventSet::any(callable $predicate): bool
EventSet::every(callable $predicate): bool
EventSet::map(callable $callback): iterable
EventSet::transform(callable $callback): self
EventSet::filter(callable $callback): self
EventSet::reduce(callable $callback, mixed $initial = null): mixed
EventSet::each(callable $callback): bool
EventSet::push(Event ...$items): self
```