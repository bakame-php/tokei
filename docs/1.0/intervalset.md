---
layout: default
title: IntervalSet
---

# IntervalSet

`Bakame\Tokei\IntervalSet` is an immutable collection of `Bakame\Tokei\Interval` instances
implementing PHP's `Countable` and `IteratorAggregate` interfaces. it represents
a collection of intervals treated as a single temporal domain.

it supports:

- interval normalization,
- union, intersection, and difference operations,
- containment checks,
- interval splitting and merging,
- iteration over intervals.

Overlapping or adjacent intervals may be merged during normalization to produce a minimal and consistent representation.

An `IntervalSet` may contain zero, one, or multiple intervals, overlapping or non-overlapping, including collapsed and circular intervals.

## Instantiation

```php
use Bakame\Tokei\IntervalSet;

IntervalSet::__construct(Interval|IntervalSet ....$intervals)
IntervalSet::chronological(Interval|IntervalSet ....$intervals): self
```

The `chronological` named constructor returns a new `IntervalSet` with its intervals ordered by ascending start time.

## Accessors

```php
IntervalSet::duration(): Duration
IntervalSet::all(): list<Interval>
IntervalSet::first(): ?Interval
IntervalSet::last(): ?Interval
IntervalSet::nth(int $nth): ?Interval
IntervalSet::get(int $nth): Interval
IntervalSet::indexOf(Interval $interval): ?int
IntervalSet::lastIndexOf(Interval $interval): ?int
IntervalSet::has(Interval ...$intervals): bool
IntervalSet::isEmpty(): bool
```

`nth` and `get` supports negative index but differ on failure:

- `nth` returns `null` on invalid offset;
- `get` throws a `TimeException` exception on invalid offset;

## Formatting

Supports the same formatting arguments as the `Interval::format` method.

```php
IntervalSet::formatAll(
    IntervalFormat $format = IntervalFormat::Iso8601StartDuration,
    ?Unit $unit = null,
): list<string> //all interval are converted to their Interval::format string representation
```

## Interacting with PHP's native Date API

```php
IntervalSet::toNative(DateTimeInterface $reference): array
```

Returns the list of  `Interval` instances converted using their `Interval::toNative` method.

## Temporal selection methods

```php
IntervalSet::next(Time $atOrAfter, SearchMode $mode, Bound $using = Bound::Start): IntervalSet
IntervalSet::previous(Time $before, SearchMode $mode, Bound $using = Bound::Start): IntervalSet
IntervalSet::nearest(Time $around, Bound $using = Bound::Start): IntervalSet
IntervalSet::includes(Time $time): IntervalSet
IntervalSet::outsideOf(Time $time): IntervalSet
IntervalSet::contains(Interval $interval): IntervalSet
IntervalSet::overlaps(Interval $interval): IntervalSet
IntervalSet::abuts(Interval $interval): IntervalSet
```

## Temporal algebra methods

```php
IntervalSet::union(Interval|IntervalSet ...$others): IntervalSet 
IntervalSet::complement(): IntervalSet
IntervalSet::intersect(IntervalSet|Interval ...$others): IntervalSet
IntervalSet::difference(IntervalSet|Interval ...$others): IntervalSet
IntervalSet::gaps(): IntervalSet
IntervalSet::sorted(Bound $by = Bound::Start, Direction $direction = Direction::Ascending): IntervalSet
IntervalSet::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): IntervalSet
IntervalSet::roundDurationTo(Unit $unit, SnapMode $mode = SnapMode::Nearest, Bound $anchor = Bound::Start): IntervalSet
IntervalSet::shift(Duration $duration): IntervalSet
```

## Collection methods

```php
IntervalSet::any(callable $callback): bool
IntervalSet::every(callable $callback): bool
IntervalSet::each(callable $callback): bool
IntervalSet::map(callable $callback): iterable
IntervalSet::transform(callable $callback): IntervalSet
IntervalSet::reduce(callable $callback, mixed $initial = null): mixed
IntervalSet::filter(callable $callback): IntervalSet
IntervalSet::sortedUsing(callable $callback): IntervalSet;
IntervalSet::push(IntervalSet|Interval ...$items): IntervalSet
IntervalSet::unshift(IntervalSet|Interval ...$items): IntervalSet
IntervalSet::remove(int ...$offset): IntervalSet
IntervalSet::replace(int $offset, Interval $newInterval): IntervalSet
IntervalSet::firstMatching(callable $callback): ?Interval
IntervalSet::lastMatching(callable $callback): ?Interval
```
