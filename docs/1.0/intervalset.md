---
layout: default
title: IntervalSet
---

# IntervalSet

`Bakame\Tokei\IntervalSet` is an immutable collection of `Bakame\Tokei\Interval` instances.
It represents a collection of intervals treated as a single temporal domain.

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

The `chronological()` named constructor returns a new `IntervalSet` with its intervals ordered by ascending start time.

## Accessors

```php
IntervalSet::totalDuration(): Duration
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

`nth()` and `get()` supports negative index but differ on failure:

- `nth()` returns `null` on invalid offset;
- `get()` throws a `TimeException` exception on invalid offset;

## Formatting

Supports the same formatting arguments as the `Interval::format()` method.

```php
IntervalSet::formatAll(IntervalFormat $format, ?Unit $unit = null): list<string>
//all interval are converted to their Interval::format string representation
```

## PHP Integration

The class implements PHP's `Countable`, `IteratorAggregate` and `Serializable` interfaces. 
For JSON serialization, the class uses the same serialization as the `Interval` class. 
The method returns a list of serialized `Interval` in their JSON representation.

```php
IntervalSet::count(): int
IntervalSet::getIterator(): iterable<Interval>
IntervalSet::jsonSerialize(): list<Interval>
```

_`(IntervalSet::jsonSerialize()` returns the same output as `IntervalSet::all()` method._

## Temporal queries methods

```php
IntervalSet::next(Time $atOrAfter, SearchMode $mode, Bound $using = Bound::Start): IntervalSet
IntervalSet::previous(Time $before, SearchMode $mode, Bound $using = Bound::Start): IntervalSet
IntervalSet::nearest(Time $around, Bound $using = Bound::Start): IntervalSet
IntervalSet::includes(Time $time): IntervalSet
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
```

## Temporal transformation methods

```php
IntervalSet::sorted(Bound $by = Bound::Start, SortDirection $direction = SortDirection::Ascending): IntervalSet
IntervalSet::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): IntervalSet
IntervalSet::shift(Duration $duration): IntervalSet
IntervalSet::expand(Duration $duration): IntervalSet
```

## Collection queries methods

```php
IntervalSet::count(): int
IntervalSet::any(callable $callback): bool
IntervalSet::every(callable $callback): bool
IntervalSet::each(callable $callback): bool
IntervalSet::reduce(callable $callback, mixed $initial = null): mixed
IntervalSet::firstMatching(callable $callback): ?Interval
IntervalSet::lastMatching(callable $callback): ?Interval
```

## Collection transformations methods

```php
IntervalSet::map(callable $callback): iterable
IntervalSet::transform(callable $callback): IntervalSet
IntervalSet::filter(callable $callback): IntervalSet
IntervalSet::sortedUsing(callable $callback): IntervalSet;
```

The `transform()` and `map()` methods are complementary.

The `map()` method applies its callback to each interval in the set and returns a value whose type is determined by the callback.
It is intended for building another representation from an `IntervalSet`.

The `transform()` method applies its callback to each interval and requires the callback to return either a valid `Interval` or an `IntervalSet`.
This guarantees that the resulting value is always a valid `IntervalSet`.

```php
$values = $set->map(
    fn (Interval $interval): Duration => $interval->duration()
);
// iterable<non-negative-int, Duration>

$transformed = $set->transform(
    fn (Interval $interval): Interval => $interval->shift($duration)
);
// IntervalSet
```

## Collection builders methods

```php
IntervalSet::append(IntervalSet|Interval ...$items): IntervalSet
IntervalSet::remove(int ...$offset): IntervalSet
IntervalSet::replace(int $offset, Interval $newInterval): IntervalSet
```