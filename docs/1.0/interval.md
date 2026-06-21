---
layout: default
title: Time
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

## Interval Type

There are 4 types of interval as defined by the relative position of their endpoints and their duration.

```php
enum IntervalType
{
    case Linear;    // returns true   (start < end)
    case Overflow;  // returns false  (start > end)
    case Circular;  // returns false  (start === end and duration is 'P1D')
    case Collapsed; // returns false  (start === end and duration is 'PT0S')
}
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
$interval->format(IntervalFormat::Iso8601StartDuration); // returns 00:00:00/PT12H
$interval->format(IntervalFormat::Iso8601StartEnd);      // returns 00:00:00/12:00:00
$interval->format(IntervalFormat::Iso8601DurationEnd);   // returns PT12H/00:00:00
$interval->format(IntervalFormat::Iso80000);             // returns [00:00:00,12:00:00)
$interval->format(IntervalFormat::Bourbaki);             // returns [00:00:00,12:00:00[
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

#### Modifying by duration and/or time

```php
Interval::startingOn(Time $time): self
Interval::endingOn(Time $time): self
Interval::expand(Duration $duration): self
Interval::shift(Duration $duration): self
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
Interval::longerThan(Interval $other): bool
Interval::longerThanOrEqual(Interval $other): bool
Interval::shorterThan(Interval $other): bool
Interval::shorterThanOrEqual(Interval $other): bool
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

## Interacting with PHP's native Date API

```php
Interval::toNative(DateTimeInterface $reference): NativeInterval
```

The reference `DateTimeInterface` object is used to compute the starting date
using `Time::applyTo` method.

The `NativeInterval` is an immutable DTO with 2 public readonly properties `start` and `end`
expressed using a `DateTimeImmutable` object. It exposes only one method `NativeInterval::duration`
which returns the difference between the starting and ending datetime value.

```php
$interval = Interval::between(
    Time::at(hour: 23, minutes: 15),
    Time::at(hour: 1, miunte: 30)
);
$native = $interval->toNative(new DateTime('2026-12-03 13:03:57'));
$native->start->format('Y-m-d H:i:s'); // '2026-12-03 23:15:00'
$native->end->format('Y-m-d H:i:s');   // '2026-12-04 01:30:00'
```

<p class="message-info">If the <code>DateTimeInterface</code> instance submitted extends the
<code>DateTimeImmutable</code> class then the return type will be of that same type
otherwise PHP's `DateTimeImmutable` is returned</p>

It is possible to generate an `Interval` instance from a `NativeInterval` but
you need to take into account that the `Interval::duration` will be affected
and will not exceed 24hours duration.

```php
$native = new NativeInterval(
    new DateTimeImmutable('2026-12-03 13:03:57'),
    new DateTimeImmutable('2026-12-05 11:19:57'),
);

$interval = Interval::fromNative($native);
$native->duration();                         // returns new DateInterval('P1DT22H16M');
$interval->format(IntervalFormat::Iso80000); // returns "[13:03:57,11:19:57)
$interval->duration->format();               // returns "PT22H16M"
```