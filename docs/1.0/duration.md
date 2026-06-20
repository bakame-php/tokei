---
layout: default
title: Duration
---

# Duration

The `Bakame\Tokei\Duration` Value Object provides utilities for working with durations

## Instantiation

The `Duration` class can be instantiated either by providing:

- each duration parts using the complementary`Duration::of` method.
- a ISO8601 duration expression.

```php
use Bakame\Tokei\Duration;

$durationA = Duration::of(hours: 2, seconds:59);
$durationB = Duration::fromFormat(notation: 'P2WT3H', format: DurationFormat::Iso8601); //2 weeks and 3 hours
$durationC = Duration::fromDateInterval(new DateInterval('PT23M3S'));
```

> [!IMPORTANT]
> Whenever an API expects a `Duration` instance, a `DateInterval` instance can be used.
> It will be converted to a `Duration` instance using the `Duration::fromDateInterval` method.

> [!IMPORTANT]
> `Duration::fromFormat` only parse ISO8601 notations with deterministic part **(ie: years and months are excluded)**
> `Duration::of` only using non-negative integer otherwise and exception will be thrown

```php
$duration = Duration::fromFormat('P2025Y3DT25s', DurationFormat::Iso8601);
// throws a Bakame\Tokei\InvalidDuration exception 
// because of the presence of the Y component
```

## Accessors

Once instantiated you can access the duration properties directly.
The object exposes a `sign` property which indicates if the original value was negative, 0 or positive.
And provides a `toMicro` method to get the microseconds based representation of the duration.

```php
$duration->microseconds; 
// returns 234_000
$duration->sign;        
 // returns 1
$duration->isZero()       
 // returns true when the duration is zero, false otherwise 
$parts = $duration->parts() 
// the parts method returns a DurationParts class with each
// populated Duration component
$parts->hours;
$parts->seconds;
$parts->microseconds;
```

## Formatting

```php
Duration::format(DurationFormat $format = DurationFormat::Iso8601): string
Duration::toDateInterval(): DateInterval
Duration::in(Unit $unit = Unit::Microseconds): int|float
```

Formatting the duration string representation is returned by the `Duration::format` with the help of the `DurationFormat` Enum

When using the `DurationFormat::Timer` the following human-readable format is used:

```php
[-]H:mm:ss[.microseconds]
```
- microseconds are optional
- negative values are prefixed with `-`

When using the `DurationFormat::Iso8601` formats the instance value is converted into a ISO8601 compatible string.
The returned string may not be compatible with PHP's `DateInterval` constructor but is valid withing the `ISO8601` extended specification.

```php
$duration = Duration::of(hours: 25, seconds: 5); 
$duration->format(DurationFormat::Iso8601); // returns 'P1D1H5S'
$duration->format(DurationFormat::Timer);   // returns '25:00:05'
```

> [!IMPORTANT]
> - **Only deterministic duration interval are used `Y`, `M` for year and month are not used**
> - to have a predictive representation `W` is not used; `7D` multiple are used instead.

```php
$duration = Duration::fromFormat('-P2W', DurationFormat::Iso8601); 
$duration->format(DurationFormat::Iso8601); // returns '-P14D'
```
Last but not least a compact format more suited for debugging is returns using the `DurationFormat::Compact` case.

```php
$duration = Duration::of(hours: 25, seconds: 5); 
$duration->format(DurationFormat::Compact); // returns '1d1h5s'
```

The `Duration` class also allows conversion in time units and in `DateInterval` instances.
The method `Duration::toDateInterval` converts the instance into a PHP `DateInterval`
instance while preserving its sign (inverted intervals are supported).

```php
$duration = Duration::of(microseconds: 3_661_234_000);
$duration->toDateInterval();          // returns DateInterval
$durationB->in(Unit::Microsecond); // returns the full duration in microseconds
$durationB->in(Unit::Hours);       // returns the full duration in hours
```

## Modifying duration

```php
Duration::abs(): Duration
Duration::negated(): Duration
Duration::increase(int $weeks = 0, int $days = 0, int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): Duration
Duration::decrease(int $weeks = 0, int $days = 0, int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): Duration
Duration::sum(Duration ...$duration): Duration
Duration::multipliedBy(int $factor): Duration
Duration::dividedBy(int $factor): Duration
Duration::chunkBy(Duration $factor): ChunkResult
Duration::roundTo(Unit $precision, RoundingStrategy $strategy): Duration
Duration::clamp(Duration $min, Duration $max): Duration
```

You can:

- make it unsigned using the `Duration::abs` method
- invert its signing using the `Duration::negate` method
- update the duration using fixed duration parts with the `Duration::increase` and `Duration::decrease` methods
- round its value to one of the unit declare on the `Bakame\Tokei\Unit` enum
- clamp its value against two other `Duration` instances
- sum multiple `Duration` instance using the `Duration::sum` method
- multiply or divide a `Duration` instance using the `Duration::multipliedBy` and `Duration::dividevBy` methods

```php
$microseconds = 3_661_500_000;
$a = Duration::of(microseconds: $microseconds);
$b = $a->roundTo(Unit::Minute, RoundingStrategy::Ceil);
$c = $b->negated();
$d = $c->decrease(minutes: 10);

echo $a->format(DurationFormat::Timer);                  // returns "1:01:01.500000"
echo $b->format(DurationFormat::Timer);                  // returns "1:01:00"
echo $c->format(DurationFormat::Timer);                  // returns "-1:01:00"
echo $c->abs()->format(DurationFormat::Timer);           // returns "1:01:00"
echo $a->sum($b, $c, $d)->format(DurationFormat::Timer); // returns "-0:09:58.500000"

$microseconds = 3_761_500_000;
$a = Duration::of(microseconds: $microseconds);
$a->format(DurationFormat::Timer);                                         // returns "1:02:41.500000"
$a->roundTo(Unit::Minute, SnapMode::Floor)->format(DurationFormat::Timer); // returns "1:02:00"
$a->roundTo(Unit::Minute, SnapMode::Ceil)->format(DurationFormat::Timer);  // returns "1:03:00"
```

> [!IMPORTANT]
> `Duration::increase` and `Duration::decrease` can only take non-negative arguments otherwise an exception will be
> throw Use `Duration::sum` to aggregate signed duration objects.

## Comparing duration

It is possible to compare duration using common methods terminology

```php
Duration::compare(Duration $that, Duration $other): int;
```
> [!IMPORTANT]
> The method is static to allow broader usage with other PHP sorting functions.

Returns:

- `-1` if shorter
- `0` if equal
- `1` if longer

Convenient methods based on `Duration::compare` are also available:

```php
$duration = Duration::of(microseconds: 3_661_500_000);
$other = Duration::fromFormat('PT1H1S');

Duration::compare($duration, $other);    //returns 1
$duration->isShorterThan($other);        // returns false
$duration->isShorterThanOrEqual($other); // returns false
$duration->equals($other);               // returns false
$duration->isLongerThan($other);         // returns true
$duration->isLongerThanOrEqual($other);  // returns true
```