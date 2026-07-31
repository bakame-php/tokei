---
layout: default
title: Interval
---

# Interval

`Bakame\Tokei\Interval` represents a start-inclusive, end-exclusive interval between two times on a 24-hour circular clock.

Intervals are immutable and support:

- circular ranges crossing midnight,
- interval algebra,
- time iteration,
- normalization,
- duration arithmetic.

The library uses half-open interval semantic where start is inclusive and end is exclusive.
If `end < start`, the interval is considered to wrap around midnight.

The library also support both collapsed and circular intervals for which `start == end`.

The distinction between them lies in their duration:

- a collapsed interval has a duration of PT0S, representing an empty interval;
- a circular interval has a duration of P1D, representing a full-day interval.

for instance:

```php
Interval::between(Time::midnight(), Time::at(10)); 
//represents 08:00 ≤ time < 10:00

Interval::between(Time::at(hour: 22), Time::at(hour: 6));
// represents 22:00 → 06:00 (next day)

Interval::collapsed(Time::midnight());
// represents 00:00:00/PT0S

Interval::circular(Time::midnight());
// represents  00:00:00/P1D
```

An interval can, thus, be defined as either:

- a continuous span of time between two points in time, or
- a continuous span of time starting at a specific point in time with a given duration.

## Instantiation

```php
Interval::between(Time $start, Time $end): self;
Interval::since(Time $start, Duration $duration): self;
Interval::until(Time $end, Duration $duration): self;
Interval::around(Time $midRange, Duration $duration): self;
Interval::collapsed(Time $at): self;
Interval::circular(Time $at): self;
Interval::fullDay(): self 
//a 24h-long instance starting at 00:00:00
// equivalent to Interval::circular(Time::midnight());
Interval::fromFormat(string $value, IntervalFormat $format, ?Unit $unit = null): self
```

## Accessors

```php
$interval = Interval::between(Time::midnight(), Time::noon());
$interval->start;     // returns Time::midnight()
$interval->end,       // returns Time::noon()
$interval->duration;  // returns Duration::of(hours: 12);
$interval->type;      // returns IntervalType::Linear
```

## Formatting

using the following Enum:

```php
enum IntervalFormat
{
    case Iso8601StartDuration;
    case Iso8601DurationEnd;
    case Iso8601StartEnd;
    case Iso80000;
    case Bourbaki;
}
```

Out of the box, to following formatting algorithm are possible:

| Format                 | String representation based on                                                                 |
|------------------------|------------------------------------------------------------------------------------------------|
| `Iso8601StartDuration` | the starting time and the interval duration                                                    |
| `Iso8601DurationEnd`   | the interval duration and the ending time                                                      |
| `Iso8601StartEnd`      | the interval starting and ending times                                                         |
| `Iso80000`             | the interval starting and ending times and the half-open bound, with ISO-8000 boundary markers |
| `Bourbaki`             | the interval starting and ending times and the half-open bound, with Bourbaki boundary markers |


```php
$interval = Interval::between(Time::midnight(), Time::noon());
$interval->format(IntervalFormat::Iso8601StartDuration);
// 00:00:00/PT12H
$interval->format(IntervalFormat::Iso8601StartEnd);
// 00:00:00/12:00:00
$interval->format(IntervalFormat::Iso8601DurationEnd);
// PT12H/00:00:00
$interval->format(IntervalFormat::Iso80000);
// [00:00:00,12:00:00)
$interval->format(IntervalFormat::Bourbaki);
// [00:00:00,12:00:00[
```

## Iterations

The `Bound` enum allow defining an anchor from which an operation can be processed in regard to intervals .

```php
enum Bound
{
    case Start;
    case End;
}
```

```php
Interval::steps(Duration $duration, Bound $from = Bound:Start): iterable<Time>
Interval::splitBy(Duration $duration, Bound $from = Bound:Start): IntervalSet
Interval::splitAt(Time ...$steps): IntervalSet
```

## Modifiers

```php
Interval::startingOn(Time $time): self
Interval::endingOn(Time $time): self
Interval::expand(Duration $duration): self
Interval::add(Duration $duration): self
Interval::sub(Duration $duration): self
Interval::shiftBound(Duration $duration, Bound $from): self
Interval::lasting(Duration $duration, Bound $from): self
Interval::roundTo(Unit $unit, SnapMode $mode): self
Interval::roundDurationTo(Unit $unit, SnapMode $mode, Bound $anchor = Bound::Start): self
Interval::complement(): self
```

## Strict Comparison

The method compares the instance endpoint as well as its duration.

```php
Interval::equals(Interval $other): bool
```

## Duration based comparison

You can use the `Duration::compare` static method to compare `Interval` instances based on their respective duration.
But the package also provide convenients method to ease instance comparison:

```php
Interval::sameDurationAs(Interval $other): bool
Interval::isLongerThan(Interval $other): bool
Interval::isLongerThanOrEqual(Interval $other): bool
Interval::isShorterThan(Interval $other): bool
Interval::isShorterThanOrEqual(Interval $other): bool
```

## Time based comparison

```php
Interval::includes(Time $time): bool
Interval::contains(Interval $other): bool
Interval::overlaps(Interval $other): bool
Interval::abuts(Interval $other): bool
Interval::intersect(Interval $other): ?self
Interval::gap(Interval $other): ?self
Interval::union(Interval $other): IntervalSet
Interval::difference(Interval $other): IntervalSet
```