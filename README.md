# Tokei

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://phpc.social/@nyamsprodd)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/tokei/workflows/build/badge.svg)](https://github.com/bakame-php/tokei/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/tokei.svg?style=flat-square)](https://github.com/bakame-php/tokei/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/tokei.svg?style=flat-square)](https://packagist.org/packages/bakame/tokei)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

**Tokei** (pronounced: [to̞ke̞ː] or [tokeː]) is a lightweight domain-focused set of immutable value objects for representing
and operating on time, durations, including, circular 24-hour intervals, and interval sets, offering
expressive temporal modeling without timezone handling.

The framework-agnostic package offers a consistent and expressive way to work with temporal values in a safe
and predictable manner.

## Installation

~~~
composer require bakame/tokei
~~~

You need:

- **PHP >= 8.3** but the latest stable version of PHP is recommended
- to be able to get the locale string version of the time you need the `ext-intl` extension or use a polyfill for `IntlDateFormatter`.

## Documentation

### Time

The `Bakame\Tokei\Time` object is designed to be, cyclic (24h wrap-around) and precision-aware (microseconds supported)

#### Instantiation

You can create a `Time` instance:

- using its time components via the `Time::at` method;
- by parsing a time string using the `Time::fromFormat` method;
- using `Time::fromOffset`; The value will represent respectively a quantity in a specified base Unit from midnight.

```php
Time::at(int $hour = 0, int $minute = 0, int $second = 0, int $microsecond = 0): Time;
Time::fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601): Time
Time::fromOffset(int $value, Unit $unit): Time
```

Here's some usage example.

```php
use Bakame\Tokei\Time;

$time = Time::at(hour: 10, minute: 30, second: 15);
$time = Time::fromFormat("10:30:15.123456", TimeFormat::Iso8601);
$time = Time::fromFormat("10h30m15s123456µs", TimeFormat::Compact);
$time = Time::fromOffset(123_456_789, Unit::Microsecond);
$time = Time::fromOffset(123_456, Unit::Millisecond);
$time = Time::fromOffset(123, Unit::Second);
$time = Time::fromOffset(456, Unit::Minute);
```

To ease instantiation, predefined instances can be obtained with the following methods:

```php 
Time::midnight(); // 00:00:00
Time::noon();     // 12:00:00
Time::endOfDay(); // 23:59:59.999999
Time::now();      // the current time
Time::now('Africa/Nairobi'); // the current time in Nairobi, Kenya
```

> [!NOTE]
> If the `Time::now()` takes an optional timezone to
> return the current time in a specific timezome. The timezone
> information is not kept.

#### Accessors

Once instantiated you can access each time component using the following methods

```php
$time = Time::fromFormat("10:30:15.123456");
$time->hour;         // returns 10
$time->minute;       // returns 30
$time->second;       // returns 15
$time->microsecond;  // returns 123456
```

#### Formatting

```php
Time::toOffset(Unit $unit): float; // returns the time value according to the provided
Time::format(TimeFormat $format = TimeFormat::Iso8601): string
Time::toLocaleString(string $locale, ?DateTimeZone $timezone = null, LocaleVerbosity $verbosity = LocaleVerbosity::Medium): string
```

To work as expected the `Time::toLocaleString` requires the presence of the Intl extension or
of its polyfill otherwise a `TimeException` will be thrown.

Example:

```php
$time = Time::at(hour: 10, minute: 30, second: 15, microsecond: 123456);
echo $time->format();
// 10:30:15.123456
echo $time->format(TimeFormat::Compact);
// 10h30m15s123456µs
echo $time->toOffset(Unit::Second);
// 37815.123456
echo $time->toLocaleString('en-US');
// 10:30:15 AM
echo $time->toLocaleString('de-DE', 'Africa/Nairobi', LocaleVerbosity::Full);
// 10:30:15 Ostafrikanische Zeit
```

#### Modifying time

Because `Time` is an immutable VO, any change to its value will return a new instance
with the updated value and leave the original object unchanged. You can modify the time
with the following methods:

- `Time::shift` will add a duration to change the time;
- `Time::with` will adjust a specific time component;
- `Time::roundTo` will adjust a specific time component;
- `Time::clamp` will adjust the time against two other time references;

```php
Time::shift(Duration $duration): Time
Time::with(?int $hour = null, ?int $minute = null, ?int $second = null, ?int $microsecond = null): Time
Time::roundTo(Unit $unit, RoundingStrategy $strategy = RoundingStrategy::Nearest): Time
Time::clamp(Time $min, Time $max): Time
```

The `shift` and `with` methods act differently in regard to wrapping around 24hours automatically.
The `Time::shift` supports wrapping whereas `Time::with` does not and instead
throws an `InvalidTime` exception instead

```php
// adding 2 hours
$time = Time::noon()->shift(Duration::of(hours: 2, minutes: 15));
$time->format(); // returns "14:15:00"

// adding 12 hours
$time = Time::noon()->shift(Duration::of(hours: 12, minutes: 15));
$time->format(); // returns "00:15:00"

// setting the hour to
$time = Time::noon()->with(hour: 2);
$time->format(); // returns "02:15:00"

Time::noon()->with(hour: 25); 
//throws a Bakame\Tokei\InvalidTime exception
```

To simplify reasoning around time you can also truncate or round its value to one of
the unit declare on the `Bakame\Tokei\Unit` enum

```php
$t = Time::fromUnitOfDay(3_150_000_000, Unit::Microsecond);
$t->format(); // returns "00:52:30"
$t->roundTo(Unit::Minutes, RoundingStrategy::Floor)->format(); // returns "00:52:00"
$t->roundTo(Unit::Minutes, RoundingStrategy::Nearest)->format();  // returns "00:53:00"
```

#### Comparing times

It is possible to compare two `Time` instances using the `Time::compareTo` method.

```php
Time::compareTo(Time $other): int;
```

the method returns:

- `-1` if earlier
- `0` if equal
- `1` if later

Convenient methods derived from `Time::compareTo` are also available to ease usage:

```php
$time = Time::at(hour: 10);
$other = Time::noon();

$time->isBefore($other);         // returns true
$time->isAfter($other);          // returns false
$time->isBeforeOrEqual($other);  // returns true
$time->isAfterOrEqual($other);   // returns false
$time->equals($other);           // returns false
```

#### Differences

The class provides two methods to account for differences between two `Time` instances:

```php
Time::diff(Time $other): Duration;
Time::distance(Time $other): Duration;
```

- the `Time::diff` returns the signed difference between both instances;
- the `Time::distance` returns the forward cyclic difference (24 wrap) between both instances;

Here's an example usage to highlight the distinction in returned
values between both differences methods:

```php
$a = Time::at(hour: 23); // 23:00
$b = Time::at(hour: 1);  // 01:00

$a->diff($b)->format(DurationFormat::Iso8601);     // returns "-PT22H"
$a->distance($b)->format(DurationFormat::Iso8601); // returns "PT2H"
```

#### Interacting with PHP's native Date API

```php
Time::fromDate(DateTimeInterface $datetime): Time
Time::applyTo(DateTimeInterface $datetime): DateTimeImmutable;
```

In one hand, it is possible to extract the time part of any `DateTimeInterface`
implementing class using the `fromDate` method. On the other hand, you
can apply the time to an `DateTimeInterface` object using the `applyTo` method

> [!NOTE]
> If the `DateTimeInterface` instance submitted extends the
`DateTimeImmutable` class then the return type will be of that same type
otherwise PHP's `DateTimeImmutable` is returned.

```php
use Bakame\Tokei\Time;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

$time = Time::fromDate(new DateTime('2025-12-27 23:00', new DateTimeZone('Africa/Nairobi'))); // 23:00

$newDate = $time->applyTo(CarbonImmutable::parse('2025-02-23'));
$newDate->format('Y-m-d H:i'); // returns '2025-02-23 23:00'
$newDate->toDateTimeString(); // returns '2025-02-23 23:00'
$newDate::class; // returns Carbon\CarbonImmutable

$altDate = $time->applyTo(Carbon::parse('2025-02-23'));
$altDate->format('Y-m-d H:i'); // returns '2025-02-23 23:00'
$altDate::class; // returns DateTimeImmutable
```

### Duration

The `Bakame\Tokei\Duration` Value Object provides utilities for working with durations

#### Instantiation

The `Duration` class can be instantiated either by providing:

- each duration parts using the complementary`Duration::of` method.
- a ISO8601 duration expression.

```php
use Bakame\Tokei\Duration;

$durationA = Duration::of(hours: 2, seconds:59);
$durationB = Duration::fromFormat(value: 'P2WT3H', format: DurationFormat::Iso8601); //2 weeks and 3 hours
$durationC = Duration::fromDateInterval(new DateInterval('PT23M3S')); 

```

> [!IMPORTANT]
> `Duration::fromFormat` only parse ISO8601 notations with deterministic part **(ie: years and months are excluded)**
> `Duration::of` only using non-negative integer otherwise and exception will be thrown

```php
$duration = Duration::fromFormat('P2025Y3DT25s', DurationFormat::Iso8601);
// throws a Bakame\Tokei\InvalidDuration exception 
// because of the presence of the Y component
```

#### Accessors

Once instantiated you can access the duration properties directly.
The object exposes a `sign` property which indicates if the original value was negative, 0 or positive.
And provides a `toMicro` method to get the microseconds based representation of the duration.

```php
$durationB->hours;        // returns 1
$durationB->minutes;      // returns 1
$durationB->seconds;      // returns 1
$durationB->microseconds; // returns 234_000
$durationB->sign;         // returns 1
$durationB->daysCount;    // returns the absolute number of complete 24-hour days contained in the duration
$durationB->weeksCount;   // returns the absolute number of complete weeks contained in the duration
$durationB->isEmpty()     // returns true when the duration is zero, false otherhwise 
```

#### Formatting

```php
Duration::format(DurationFormat $format = DurationFormat::Iso8601): string
Duration::toDateInterval(): DateInterval
Duration::total(Unit $unit = Unit::Microseconds): float
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
> - **Only deterministic duration interval are used `Y`, `M` for month are not used**
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
$durationB->total(Unit::Microsecond); // returns the full duration in microseconds
$durationB->total(Unit::Hours);       // returns the full duration in hours
```

#### Modifying duration

```php
Duration::abs(): Duration
Duration::negated(): Duration
Duration::increase(int $weeks = 0, int $days = 0, int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): Duration
Duration::decrease(int $weeks = 0, int $days = 0, int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): Duration
Duration::sum(Duration ...$duration): Duration
Duration::multipliedBy(int $factor): Duration
Duration::dividedBy(int $factor): Duration
Duration::roundTo(Unit $precision, RoundingStrategy $strategy): Duration
Duration::clamp(Duration $min, Duration $max): Duration
```

You can:

- make it unsigned using the `Duration::abs` method
- invert its signing using the `Duration::negate` method
- update the duration using fixed duration parts with the `Duration::increment` and `Duration::decrement` methods
- round its value to one of the unit declare on the `Bakame\Tokei\Unit` enum
- clamp its value against two other `Duration` instances
- sum multiple `Duration` instance using the `Duration::sum` method
- multiply or divide a `Duration` instance using the `Duration::multipliedBy` and `Duration::dividevBy` methods

```php
$microseconds = 3_661_500_000;
$a = Duration::of(microseconds: $microseconds);
$b = $a->roundTo(Unit::Minute, RoundingStrategy::Ceil);
$c = $b->negate();
$d = $c->decrement(minutes: 10);

echo $a->format(DurationFormat::Timer);                  // returns "1:01:01.500000"
echo $b->format(DurationFormat::Timer);                  // returns "1:01:00"
echo $c->format(DurationFormat::Timer);                  // returns "-1:01:00"
echo $c->abs()->format(DurationFormat::Timer);           // returns "1:01:00"
echo $a->sum($b, $c, $d)->format(DurationFormat::Timer); // returns "-0:09:58.500000"

$microseconds = 3_761_500_000;
$a = Duration::of(microseconds: $microseconds);
$a->format(DurationFormat::Timer);                                               // returns "1:02:41.500000"
$a->roundTo(Unit::Minute, RoudingMode::Truncate)->format(DurationFormat::Timer); // returns "1:02:00"
$a->roundTo(Unit::Minute, RoudingMode::Round)->format(DurationFormat::Timer);    // returns "1:03:00"
```

> [!IMPORTANT]
> `Duration::increment` and `Duration::decrement` can only take non-negative arguments otherwise an exception will be
> throw Use `Duration::sum` to aggregate signed duration objects.

#### Comparing duration

It is possible to compare duration using common methods terminology

```php
Duration::compareTo(Duration $other): int;
```

Returns:

- `-1` if shorter
- `0` if equal
- `1` if longer

Convenient methods based on `Duration::compareTo` are also available:

```php
$duration = Duration::of(microseconds: 3_661_500_000);
$other = Duration::fromFormat('PT1H1S');

$duration->isShorterThan($other);        // returns false
$duration->isShorterThanOrEqual($other); // returns false
$duration->equals($other);               // returns false
$duration->isLongerThan($other);         // returns true
$duration->isLongerThanOrEqual($other);  // returns true
```

### Interval

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
Interval::between(Time::midnight(), Time::at(10)); //represents 08:00 ≤ time < 10:00
Interval::between(Time::at(hour: 22), Time::at(hour: 6)); // represents 22:00 → 06:00 (next day)
Interval::collapsed(Time::midnight()); // represents 00:00:00/PT0S
Interval::circular(Time::midnight());  // represents  00:00:00/P1D
```

An interval can, thus, be defined as either:

- a continuous span of time between two points in time, or
- a continuous span of time starting at a specific point in time with a given duration.

#### Instantiation

```php
Interval::between(Time $start, Time $end): self;
Interval::since(Time $start, Duration $duration): self;
Interval::until(Time $end, Duration $duration): self;
Interval::around(Time $midRange, Duration $duration): self;
Interval::collapsed(Time $at): self;
Interval::circular(Time $at): self;
Interval::fullDay(): self //a 24h-long instance starting at 00:00:00
Interval::fromFormat(string $value, IntervalFormat $format, ?Unit $unit = null): self
```

#### Accessors

```php
$interval = Interval::between(Time::midnight(), Time::noon());
$interval->start;     // returns Time::midnight()
$interval->end,       // returns Time::noon()
$interval->duration;  // returns Duration::of(hours: 12);
$interval->type;      // returns IntervalType
```

#### Interval Type

```php
enum IntervalType
{
    case Linear;    // returns true   (start < end)
    case Overflow;  // returns false  (start > end)
    case Circular;  // returns false  (start === end and duration is 'P1D')
    case Collapsed; // returns false  (start === end and duration is 'PT0S')
}
```

#### Formatting

using the following Enum:

```php
enum IntervalFormat
{
    case Iso8601StartDuration;
    case Iso8601DurationEnd;
    case Iso8601StartEnd;
    case Iso8601;
    case Iso80000;
    case Bourbaki;
}
```

Out of the box, to following formatting algorithm are possible:

- `Iso8601StartDuration` returns a string representation based on the starting time and the interval duration;
- `Iso8601DurationEnd` returns a string representation based on the interval duration and the ending time;
- `Iso8601StartEnd` returns a string representation based on the interval starting and ending times;
- `Iso8601` returns the same representation as `Iso8601StartDuration`;
- `Iso80000` returns a string representation based on the interval starting and ending times and the half-open bound;
- `Bourbaki` returns a string representation based on the interval starting and ending times and the half-open bound, with different boundary markers;

```php
$interval = Interval::between(Time::midnight(), Time::noon());
$interval->format(IntervalFormat::Iso8601StartDuration); // returns 00:00:00/PT12H
$interval->format(IntervalFormat::Iso8601StartEnd);      // returns 00:00:00/12:00:00
$interval->format(IntervalFormat::Iso8601DurationEnd);   // returns PT12H?00:00:00
$interval->format(IntervalFormat::Iso80000);             // returns [00:00:00,12:00:00)
$interval->format(IntervalFormat::Bourbaki);             // returns [00:00:00,12:00:00[
```

> [!IMPORTANT]
> The same Enum is used when using `Duration::fromFormat`, the only difference is on instantiation,
> The `IntervalFormat::Iso8601` will be lenient and accept any ISO8601 supported format.

#### Iterations

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
Interval::roundTo(Unit $unit, RoundingStrategy $strategy): self
Interval::expand(Duration $duration): self
Interval::shift(Duration $duration): self
Interval::shiftBound(Duration $duration, Bound $from): self
Interval::lasting(Duration $duration, Bound $from): self
Interval::complement(): self
```

#### Strict Comparison

The method compares the instance endpoint as well as its duration. 

```php
Interval::equals(Interval $other): bool
```

#### Duration based comparison

```php
Interval::compareDurationTo(Interval $other): int
Interval::sameDurationAs(Interval $other): bool
Interval::longerThan(Interval $other): bool
Interval::longerThanOrEqual(Interval $other): bool
Interval::shorterThan(Interval $other): bool
Interval::shorterThanOrEqual(Interval $other): bool
```

#### Time based comparison

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

#### Interacting with PHP's native Date API

```php
Interval::toNative(DateTimeInterface $reference): array
// returns array{startDate: DateTimeImmuable, interval: DateInterval}
```

The reference `DateTimeInterface` object is used to compute the starting date
using `Time::applyTo` method.

### IntervalSet

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

An `IntervalSet` may contain zero, one, or multiple non-overlapping intervals, including collapsed and circular intervals.

#### Instantiation

```php
use Bakame\Tokei\IntervalSet;

IntervalSet::__constrcut(Interval|IntervalSet ....$interval)
```

#### Accessors

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

#### Formatting

Supports the same formatting arguments as the `Interval::format` method.

```php
IntervalSet::allFormatted(
    IntervalFormat $format = IntervalFormat::Iso8601StartDuration,
    ?Unit $unit = null,
): list<string> //all interval are converted to their Interval::format string representation
```

#### Interacting with PHP's native Date API

```php
IntervalSet::allNative(DateTimeInterface $reference): array
```

Returns the list of  `Interval` instances converted using their `Interval::toNative` method.

#### Interval methods

```php
IntervalSet::union(): IntervalSet 
IntervalSet::complement(): IntervalSet
IntervalSet::intersect(IntervalSet|Interval ...$others): IntervalSet
IntervalSet::difference(IntervalSet|Interval ...$others): IntervalSet
IntervalSet::gaps(): IntervalSet
IntervalSet::sorted(Bound $sortBound = Bound::Start, SortDirection|string $sortDirection = 'asc'): IntervalSet;
```

#### Collection methods

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

## Testing

The library has:

- a [PHPUnit](https://phpunit.de) test suite.
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/tokei/graphs/contributors)
