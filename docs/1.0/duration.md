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

<p class="message-info">Whenever an API expects a `Duration` instance, a <code>DateInterval</code> instance
can be used. It will be converted to a `Duration` instance using the <code>Duration::fromDateInterval</code> method.</p>

<p class="message-warning"><code>Duration::fromFormat</code> only parse ISO8601 notations with deterministic part 
<strong>(ie: years and months are excluded)</strong>. <code>Duration::of</code> only using non-negative integer
otherwise and exception will be thrown</p>

```php
$duration = Duration::fromFormat('P2025Y3DT25S', DurationFormat::Iso8601);
// throws a Bakame\Tokei\InvalidDuration exception 
// because of the presence of the Y component
```
Four specific named constructors are added to represent special semantic values.

- `Duration::zero()` represents a duration of 0 second.
- `Duration::fullDay()` represents a complete 24-hour clock duration
- `Duration::min()` represents the smallest representable duration
- `Duration::max()` represents the largest representable duration

## Accessors

Once instantiated you can access the duration properties directly.
The object exposes a `sign` property which indicates if the original value was negative, `0` or positive.
It provides properties to access the duration 

- `seconds` : the number of seconds in this duration
- `nanoseconds` : the number of fractions in this duration expressed in nanoseconds

And the `in()` method gives access to the total duration in a specific unit

Depending on the duration, the returned value can be an integer or a float.

```php
$duration = Duration::fromDateInterval(new DateInterval('PT23M3S'));
$duration->in(Unit::Microsecond); // returns 1383_000_000
$duration->in(Unit::Minute);      // returns 23.05
$duration->sign;                  // returns 1
$duration->isZero() ;             // returns false
$duration->nanoseconds;          // returns 0
$duration->seconds;               // returns 1383
```

## Formatting

```php
Duration::format(DurationFormat $format): string
Duration::toDateInterval(): DateInterval
Duration::toNumberString(Unit $unit, int $precision): string
```

Formatting the duration string representation is returned by the `Duration::format` with the help of the `DurationFormat` Enum

When using the `DurationFormat::Timer` the following human-readable format is used:

```php
[-]HH:mm:ss[.nanoseconds]
```
- nanoseconds are optional
- negative values are prefixed with `-`

When using the `DurationFormat::Iso8601` formats the instance value is converted into a ISO8601 compatible string.
The returned string may not be compatible with PHP's `DateInterval` constructor but is valid withing the `ISO8601` extended specification.

```php
$duration = Duration::of(hours: 23, seconds: 3); 
$duration->format(DurationFormat::Iso8601); // returns 'P1DT1H5S'
$duration->format(DurationFormat::Timer);   // returns '25:00:05'
$duration->toNumberString(Unit:Minute);     // returns '1380'
$duration->toNumberString(Unit:Minute, 2);  // returns '1380.05'
```

<div class="message-warning">
<ul>
<li><strong>Only deterministic duration interval are used <code>Y</code>, <code>M</code> for year and month are not used</strong></li>
<li>to have a predictive representation <code>W</code> is not used; <code>168H</code> multiple are used instead.</li>
</ul>
</div>

```php
$duration = Duration::fromFormat('-P2W', DurationFormat::Iso8601); 
$duration->format(DurationFormat::Iso8601); // returns '-PT336H'
```
Last but not least a compact format more suited for debugging is returns using the `DurationFormat::Compact` case.

```php
$duration = Duration::fromFormat('-PT25H0.5S', DurationFormat::Iso8601); 
$duration->format(DurationFormat::Compact); // returns '-1d1h500ms'
```

The `Duration` class also allows conversion in time units and in `DateInterval` instances.
The method `Duration::toDateInterval` converts the instance into a PHP `DateInterval`
instance while preserving its sign (inverted intervals are supported).

```php
$duration = Duration::of(microseconds: 3_661_234_000);
$duration->toDateInterval();
// returns DateInterval
```

You can obtain a duration as a numeric string using the 
`Duration::toNumberString()` method. It complements `Duration::in()`
by providing control over the textual representation of
the converted value.

```php
$duration = Duration::of(hours: 23, seconds: 3);
$duration->toNumberString(Unit:Minute);
// returns '1380'
$duration->toNumberString(Unit:Minute, precision: 2); 
// returns '1380.05'
```

The `precision` argument specifies the number of fractional digits to include
in the resulting string. If the converted value is an integer, the argument is ignored.

Passing a negative value for `precision` throws a `ValueError`.

`Duration::toNumberString()` is intended solely for formatting the numeric
representation of a duration. If you need to round or truncate the duration
before converting it to a string, use `Duration::roundTo()` first.
This keeps rounding behavior explicit and ensures consistent, reproducible results.

## Modifying duration

```php
Duration::absolute(): Duration
Duration::negate(): Duration
Duration::add(Duration ...$duration): Duration
Duration::sub(Duration ...$duration): Duration
Duration::multiplyBy(int $factor): Duration
Duration::divideBy(int $factor): Duration
Duration::divideInto(Duration $factor): DivisionResult
Duration::modulo(Duration $factor): DUration
Duration::roundTo(Unit $precision, SnapMode $mode): Duration
Duration::clamp(Duration $min, Duration $max): Duration
```

You can:

- make it unsigned using the `Duration::absolute` method
- invert its signing using the `Duration::negate` method
- update the duration using multiple instances with `Duration::add` and `Duration::sub` methods
- round its value to one of the unit declare on the `Bakame\Tokei\Unit` enum
- clamp its value against two other `Duration` instances
- multiply or divide a `Duration` instance using the `Duration::multiplyBy`, `Duration::divideBy` and  `Duration::divideInto` methods

```php
$microseconds = 3_661_500_000;
$a = Duration::of(microseconds: $microseconds);
$b = $a->roundTo(Unit::Minute, RoundingStrategy::Ceil);
$c = $b->negate();
$d = $c->sub(Duration::of(minutes: 10));

echo $a->format(DurationFormat::Timer);
// returns "1:01:01.500000"
echo $b->format(DurationFormat::Timer);
// returns "1:01:00"
echo $c->format(DurationFormat::Timer);
// returns "-1:01:00"
echo $c->absolute()->format(DurationFormat::Timer);
// returns "1:01:00"
echo $a->add($b, $c, $d)->format(DurationFormat::Timer);
// returns "-0:09:58.500000"

$microseconds = 3_761_500_000;
$a = Duration::of(microseconds: $microseconds);
$a->format(DurationFormat::Timer);
// returns "1:02:41.500000"
$a->roundTo(Unit::Minute, SnapMode::Floor)->format(DurationFormat::Timer);
// returns "1:02:00"
$a->roundTo(Unit::Minute, SnapMode::Ceil)->format(DurationFormat::Timer);
// returns "1:03:00"

$duration = Duration::fromFormat('-PT5H30M', DurationFormat::Iso8601);
$oneHour = Duration::of(hours: 1);
$result = $duration->divideInto($oneHour);

$duration->format(DurationFormat::Iso8601);
// returns '-PT5H30M'
$result->quotient;
// returns '-5'
$result->remainder->format(DurationFormat::Iso8601);
// returns '-PT30M'
$oneHour
    ->multiplyBy($result->quotient)
    ->add($result->remainder)
    ->equals($duration);
// returns true
```

<p class="message-info">Use <code>Duration::add</code> or <code>Duration::sub</code> to aggregate signed duration objects.</p>

Support for dividing one duration by another is enabled using  the `divideInto()` or `modulo()` method.
The `divideInto()` method returns a DTO, `DivisionResult`, exposing the quotient and remainder,
giving developers the flexibility to access the result either through its properties
or by using array destructuring on `DivisionResult`. 

```php
$duration = Duration::fromFormat('-PT5H30M', DurationFormat::Iso8601);
$oneHour = Duration::of(hours: 1);
$result = $duration->divideInto($oneHour);
$result->quotient;
// returns '-5'
$result->remainder->format(DurationFormat::Iso8601);
// returns '-PT30M'

[$quotient, $remainder] = $result;
$quotient;
// returns '-5'
$remainder->format(DurationFormat::Iso8601);
// returns '-PT30M'
```
The `modulo()` method complements `divideInto()` by providing an easy way to retrieve
the remainder of a duration division. This is especially useful when dealing with
circular ranges, as the result of `modulo()` is always a non-negative `Duration` instance

```php
$duration = Duration::of(hours: 3, seconds: 35);
$factor = Duration::of(hours: 1);

echo $duration
    ->modulo($factor)
    ->format(DurationFormat::Compact), PHP_EOL;
//returns 35s;

echo $duration
    ->divideInto($factor)
    ->remainder
    ->format(DurationFormat::Compact), PHP_EOL;
//returns 35s;

echo $duration
    ->negated()
    ->modulo($factor)
    ->format(DurationFormat::Compact), PHP_EOL;
//returns 59m25s;

echo $duration
    ->negated()
    ->divideInto($factor)
    ->remainder
    ->format(DurationFormat::Compact), PHP_EOL;
//returns -35s;
```

## Comparing duration

It is possible to compare duration using common methods terminology

```php
Duration::compare(Duration $that, Duration $other): int;
```
<p class="message-notice">The method is static to allow broader usage with other PHP sorting functions.</p>

Returns:

- `-1` if shorter
- `0` if equal
- `1` if longer

Convenient methods based on <code>Duration::compare</code> are also available:

```php
$duration = Duration::of(microseconds: 3_661_500_000);
$other = Duration::fromFormat('PT1H1S', DurationFormat::Iso8601);

Duration::compare($duration, $other);    //returns 1
$duration->isShorterThan($other);        // returns false
$duration->isShorterThanOrEqual($other); // returns false
$duration->equals($other);               // returns false
$duration->isLongerThan($other);         // returns true
$duration->isLongerThanOrEqual($other);  // returns true
```