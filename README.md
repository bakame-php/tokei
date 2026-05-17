# Tokei

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://phpc.social/@nyamsprodd)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/tokei/workflows/build/badge.svg)](https://github.com/bakame-php/tokei/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/tokei.svg?style=flat-square)](https://github.com/bakame-php/tokei/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/tokei.svg?style=flat-square)](https://packagist.org/packages/bakame/tokei)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

**Tokei** (pronounced: [to̞ke̞ː] or [tokeː]) is a lightweight package to work with time
and duration without timezone information attached to them in PHP. 

The framework-agnostic package provides strict, immutable value objects designed for
precise and reliable time handling. This offers a consistent and expressive way to
work with temporal values in a safe and predictable manner.

## Installation

~~~
composer require bakame/tokei
~~~

You need:

- **PHP >= 8.3** but the latest stable version of PHP is recommended


## Documentation

### Time

The `Bakame\Tokei\Time` object is designed to be, cyclic (24h wrap-around) and precision-aware (microseconds supported)

#### Instantiation

You can create a `Time` instance:

- using its time components via the `Time::at` method;
- by parsing a time string using the `Time::parse` method;
- using `Time::atMicroOfDay`, `Time::atMilliOfDay`, `Time::atSecondOfDay` or `Time::atMinuteOfDay`; The value will represent respectively the microseconds, milliseconds, seconds or minutes from midnight.

```php
Time::at(int $hour = 0, int $minute = 0, int $second = 0, int $microsecond = 0): Time;
Time::parse(string $value,  string $separator = ':'): ?Time
Time::atMicroOfDay(int $value): Time
Time::atMilliOfDay(int $value): Time
Time::atSecondOfDay(int $value): Time
Time::atMinuteOfDay(int $value): Time
```

Here's some usage example.

```php
use Bakame\Tokei\Time;

$timeB = Time::parse("10:30:15.123456");
$timeA = Time::at(hour: 10, minute: 30, second: 15);
$timeC = Time::atMicroOfDay(123_456_789);
$timeC = Time::atMilliOfDay(123_456); 
$timeC = Time::atSecondOfDay(123); 
$timeC = Time::atMinuteOfDay(456); 
```

> [!WARNING]
> On failure, with `Time::parse`, `null` is returned instead of an exception being thrown.

To ease instantiation, predefined instances can be obtained with the following methods:

```php
Time::min();      // 00:00:00
Time::max();      // 23:59:59.999999
Time::noon();     // 12:00:00
Time::midnight(); // alias of min()
```

#### Accessors

Once instantiated you can access each time component using the following methods

```php
$time = Time::parse("10:30:15.123456");
$time->hour;         // returns 10
$time->minute;       // returns 30
$time->second;       // returns 15
$time->microsecond;  // returns 123456
```

#### Formatting

```php
Time::toMicroOfDay();  // returns 37_815_123_456 (the microseconds offset since midnight)
Time::format(
    string $separator = ':',
    PaddingMode $padding = PaddingMode::Padded,
    SubSecondDisplay $subSecond = SubSecondDisplay::Auto,
): string
```

Example:

```php
$time = Time::parse("10:30:15.123456");

$time->format(subSecond: SubSecondDisplay::Auto);   // 10:30:15.123456 (default)
$time->format(subSecond: SubSecondDisplay::Never);  // 10:30:15
$time->format(subSecond: SubSecondDisplay::Always); // 10:30:15.123456
$time->toMicroOfDay(); // 37815123456
```

- `SubSecondDisplay::Auto` only show the microseconds if their value is less than `0`.
- `SubSecondDisplay::Never` never show the fraction.
- `SubSecondDisplay::Always` always show the fraction..

#### Modifying time

Because `Time` is an immutable VO, any change to its value will return a new instance
with the updated value and leave the original object unchanged. You can modify the time
with the following methods:

- `Time::add` will add a duration to change the time;
- `Time::with` will adjust a specif time component;

```php
Time::add(Duration $duration): Time
Time::with(?int $hour = null, ?int $minute = null, ?int $second = null, ?int $microsecond = null): Time
```

Both methods act differently in regard to wrapping around 24hours automatically.
The `Time::add` supports wrapping whereas `Time::with` does not and instead
throws an `InvalidTime` exception instead

```php
// adding 2 hours
$time = Time::noon()->add(Duration::of(hours: 2, minutes: 15));
$time->format(); // returns "14:15:00"

// adding 12 hours
$time = Time::noon()->add(Duration::of(hours: 12, minutes: 15));
$time->format(); // returns "00:15:00"

// setting the hour to
$time = Time::noon()->with(hour: 2);
$time->format(); // returns "02:15:00"

Time::noon()->with(hours: 25); 
//throws a Bakame\Tokei\InvalidTime exception
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
$time = Time::at(hours: 10);
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
$a = Time::at(hours: 23); // 23:00
$b = Time::at(hours: 1);  // 01:00

$a->diff($b)->toIso8601();     // returns "-PT22H"
$a->distance($b)->toIso8601(); // returns "PT2H"
```

#### Interacting with PHP's native Date API

```php
Time::extractFrom(DateTimeInterface $datetime): Time
Time::applyTo(DateTimeInterface $datetime): DateTimeImmutable;
```

In one hand, it is possible to extract the time part of any `DateTimeInterface`
implementing class using the `extractFrom` method. On the other hand, you
can apply the time to an `DateTimeInterface` object using the `applyTo` method

> [!NOTE]
> If the `DateTimeInterface` instance submitted extends the
`DateTimeImmutable` class then the return type will be of that same type
otherwise PHP's `DateTimeImmutable` is returned.


```php
use Bakame\Tokei\Time;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

$time = Time::extractFrom(new DateTime('2025-12-27 23:00', new DateTimeZone('Africa/Nairobi'))); // 23:00

$newDate = $time->applyTo(CarbonImmutable::parse('2025-02-23'));
$newDate->toDateTimeString(); // returns '2025-02-23 23:00'
$newDate::class; // returns CarbonImmutable

$altDate = $time->applyTo(Carbon::parse('2025-02-23'));
$altDate->toDateTimeString(); // returns '2025-02-23 23:00'
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
$durationB = Duration::fromIso8601('P2WT3H'); //2 weeks and 3 hours
```

> [!IMPORTANT]
> `Duration::fromIso8601` only parse ISO8601 notations with deterministic part **(ie: years and months are excluded)**

```php
$duration = Duration::fromIso8601('-P2YT3H');
// throws a Bakame\Tokei\InvalidDuration exception 
// because of the presence of the Y component
```

#### Accessors

Once instantiated you can access the duration properties directly.
The object exposes a `inverted` property which indicates if the original value was negative or not.
And provides a `toMicro` method to get the microseconds based representation of the duration.

```php
$durationB->hours;        // returns 1
$durationB->minutes;      // returns 1
$durationB->seconds;      // returns 1
$durationB->microseconds; // returns 234_000
$durationB->inverted;     // returns false
$durationB->isEmpty()     // returns true when the duration is zero, false otherhwise 
```

#### Formatting

```php
Duration::toClockFormat(): string
```
Formats the instance value into a human-readable string. The following format is used:

```php
[-]H:mm:ss[.microseconds]
```
- microseconds are optional (only shown if non-zero)
- negative values are prefixed with `-`

```php
Duration::toIso8601(): string
```
Formats the instance value into a ISO8601 compatible string. The returned string
may not be compatible with PHP's `DateInterval` constructor but is valid
withing the `ISO8601` specification.

```php
$duration = Duration::of(hours: 25, seconds: 5); 
$duration->toIso8601(); // returns 'P1D1H5S'
```

> [!IMPORTANT]
> - **Only deterministic duration interval are used `Y`, `M` for month are not used**
> - to have a predictive representation `W` is not used; `7D` multiple are used instead.

```php
$duration = Duration::fromIso8610('-P2W'); 
$duration->toIso8601(); // returns '-P14D'
```

The `Duration` class also allows conversion in microseconds and in `DateInterval` instances.

```php
Duration::toDateInterval(): DateInterval
Duration::toMicro(): int
```

The method `Duration::toDateInterval` converts the instance into a PHP `DateInterval`
instance while preserving its sign (inverted intervals are supported).

```php
$duration = Duration::of(microseconds: 3_661_234_000);
$duration->toDateInterval(); // returns DateInterval
$durationB->toMicro();       // returns the full duration in microseconds format
```

#### Modifying duration

```php
Duration::abs(): Duration
Duration::negate(): Duration
Duration::truncateTo(Precision $precision): Duration
Duration::add(Duration ...$duration): Duration
Duration::increment(int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): Duration
```

You can:

- make it unsigned using the `Duration::abs` method
- invert its signing using the `Duration::negate` method
- update the duration using fixed duration parts `Duration::increment` method
- truncate its value to one of the unit declare on the `Bakame\Tokei\Precision` enum
- sum multiple `Duration` instance using the `Duration::sum` method

```php
$microseconds = 3_661_500_000;
$a = Duration::of(microseconds: $microseconds);
$b = $a->truncateTo(Precision::Minutes);
$c = $b->negate();
$d = $c->increment(minutes: -10);

echo $a->toClockFormat();                  // returns "1:01:01.500000"
echo $b->toClockFormat();                  // returns "1:01:00"
echo $c->toClockFormat();                  // returns "-1:01:00"
echo $c->abs()->toClockFormat();           // returns "1:01:00"
echo $a->sum($b, $c, $d)->toClockFormat(); // returns "-0:09:58.500000"
```

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
$other = Duration::fromIso8601('PT1H1S');

$duration->isShorterThan($other);        // returns false
$duration->isShorterThanOrEqual($other); // returns false
$duration->equals($other);               // returns false
$duration->isLongerThan($other);         // returns true
$duration->isLongerThanOrEqual($other);  // returns true
```

### Interval

`Bakame\Tokei\Interval` represents a start-inclusive, end-exclusive interval between two times on a 24-hour circular clock.

Intervals are immutable and support:

= circular ranges crossing midnight,
- interval algebra,
- time iteration,
- normalization,
- duration arithmetic.

The library uses half-open interval semantic

```bash
[start, end)
```

#### Instantiation

```php
Interval::between(Time $start, Time $end): self;
Interval::since(Time $start, Duration $duration): self;
Interval::until(Time $end, Duration $duration): self;
Interval::around(Time $midRange, Duration $duration): self;
Interval::collapsed(Time $at): self;
Interval::circular(Time $at): self;
```

Helper instances for business hours

```php
Interval::morning(): self   // returns [06:00,12:00)
Interval::afternoon(): self // returns [12:00,18:00)
Interval::evening(): self   // returns [18:00,22:00)
Interval::night(): self     // returns [22:00,6:00)
Interval::day(): self       // returns [06:00,22:00)
Interval::fullDay(): self   // returns [00:00,00:00) with a duration of 24h
```

#### Accessors

```php
$interval = Interval::between(Time::midnight(), Time::noon());
$interval->start;         // returns Time::midnight()
$interval->end,           // returns Time::noon()
$interval->duration;      // returns Duration::of(hours: 12);
$interval->isCircular();  // returns false
$interval->isCollapsed(); // returns false
```

#### Iterations

```php
Interval::splitForward(Duration $step): iterable<Interval>
Interval::splitBackward(Duration $step): iterable<Interval>
Interval::rangeForward(Duration $step): iterable<Time>
Interval::rangeBackward(Duration $step): iterable<Time>
```

#### Modifying by duration

```php
Interval::shift(Duration $duration): self
Interval::shiftStart(Duration $duration): self
Interval::shiftEnd(Duration $duration): self
Interval::lastingFromStart(Duration $duration): self
Interval::lastingFromEnd(Duration $duration): self
Interval::expand(Duration $duration): self
```

#### Modifying by time

```php
Interval::startingOn(Time $time): self
Interval::endingOn(Time $time): self
Interval::complement(): self
Interval::splitAt(Time $time): IntervalSet
```

#### Comparison

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
Interval::union(Interval ...$other): IntervalSet
Interval::difference(Interval $other): IntervalSet
```

### IntervalSet

`Bakame\Tokei\IntervalSet` is an immutable collection of `Bakame\Tokei\Interval` instances implementing PHP's `Countable` and `IteratorAggregate` interfaces

#### Instantiation

```php
use Bakame\Tokei\IntervalSet;

IntervalSet::__constrcut(Interval|IntervalSet ....$interval)
```

#### Accessors

```php
IntervalSet::all(): list<Interval>
IntervalSet::isEmpty(): bool
IntervalSet::get(int $nth): ?Interval
IntervalSet::first(): ?Interval
IntervalSet::last(): ?Interval
```

#### Formatting

```php
IntervalSet::allFormatted(
    string $separator = ':',
    PaddingMode $padding = PaddingMode::Padded,
    SubSecondDisplay $subSecond = SubSecondDisplay::Auto,
): list<string>
```

#### Modifiers

```php
IntervalSet::push(IntervalSet|Interval ...$items): IntervalSet 
```

#### Interval methods

```php
IntervalSet::union(): IntervalSet 
IntervalSet::differnces(IntervalSet|Interval ...$other): IntervalSet

IntervalSet::includes(Time $time): bool
IntervalSet::includesAll(Time $time): bool
IntervalSet::including(Time $time): IntervalSet

IntervalSet::overlaps(Interval $interval): bool
IntervalSet::overlapsAll(nterval $interval): bool
IntervalSet::overlapping(nterval $interval): IntervalSet

IntervalSet::contains(Interval $interval): bool
IntervalSet::containsAll(nterval $interval): bool
IntervalSet::containing(nterval $interval): IntervalSet

IntervalSet::abuts(Interval $interval): bool
IntervalSet::abutsAll(nterval $interval): bool
IntervalSet::abutting(nterval $interval): IntervalSet
```

#### Collection methods

```php
IntervalSet::exists(callable $callback): bool
IntervalSet::forAll(callable $callback): bool
IntervalSet::filter(callable $callback): IntervalSet
IntervalSet::map(callable $callback): iterable
IntervalSet::reduce(callable $callback, mixed $initial = null): IntervalSet
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
