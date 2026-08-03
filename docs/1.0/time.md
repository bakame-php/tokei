---
layout: default
title: Time
---

# Time

The `Bakame\Tokei\Time` object is designed to be, cyclic (24h wrap-around) and precision-aware (nanooseconds supported)

## Instantiation

You can create a `Time` instance:

- using its time components via the `Time::at` method;
- by parsing a time string using the `Time::fromFormat` method;
- using `Time::sinceMidnight`; The value will be represented as a [Duration](/1.0/duration/) from midnight.

```php
Time::at(
    int $hour = 0,
    int $minute = 0,
    int $second = 0,
    int $nanosecond = 0
): Time;

Time::fromFormat(
    string $value,
    TimeFormat $format
): Time

Time::sinceMidnight(Duration $value): Time
```

Here's some usage example.

```php
use Bakame\Tokei\Time;

$time = Time::at(hour: 10, minute: 30, second: 15);
$time = Time::fromFormat("10:30:15.123456", TimeFormat::Clock);
$time = Time::fromFormat("10h30m15s123456µs", TimeFormat::Compact);
$time = Time::sinceMidnight(Duration::of(microseconds: 123_456_789));
$time = Time::sinceMidnight(Duration::of(milliseconds: 123_456));
$time = Time::sinceMidnight(Duration::of(seconds: 123));
$time = Time::sinceMidnight(Duration::of(minutes: 1)->negated()); // returns "23:59:00"
```

To ease instantiation, predefined instances can be obtained with the following methods:

```php 
Time::midnight(); // 00:00:00
Time::noon();     // 12:00:00
Time::endOfDay(); // 23:59:59.999999999
```

## Accessors

Once instantiated you can access each time component  directly.

- `nanosecond`
- `second`
- `minute`
- `hour`

The `offset()` method allow access the time total duration since midnight.

```php
$time = Time::fromFormat("10:30:15.123456", TimeFormat::Clock);
$time->hour;
// 10
$time->minute;
// 30
$time->second;
// 15
$time->nanosecond;
// 123456000
$time->offset()->in(Unit::Minute);
// 630.2520576
$time->offset()->in(Unit::Second);
// 37815.123456
```

## Formatting

```php
Time::format(TimeFormat $format = TimeFormat::Clock): string
Time::toLocaleString(
    string $locale,
    DateTimeZone|string|null $timezone = null,
    LocaleVerbosity $verbosity = LocaleVerbosity::Medium
): string
```

<p class="message-notice">To work as expected the <code>Time::toLocaleString</code> requires the presence
of the Intl extension or of its polyfill otherwise a <code>TimeException</code> will be thrown.</p>

Example:

```php
$time = Time::at(hour: 10, minute: 30, second: 15, microsecond: 123456);
echo $time->format(TimeFormat::Clock);
// 10:30:15.123456
echo $time->format(TimeFormat::Compact);
// 10h30m15s123456µs
echo $time->offset()->in(Unit::Second);
// 37815.123456
echo $time->toLocaleString('en-US');
// 10:30:15 AM
echo $time->toLocaleString('de-DE', 'Africa/Nairobi', LocaleVerbosity::Full);
// 10:30:15 Ostafrikanische Zeit
```

## Modifying time

Because `Time` is an immutable VO, any change to its value will return a new instance
with the updated value and leave the original object unchanged. You can modify the time
with the following methods:

- `Time::add` and `Time::sub` will adjust the time using a duration;
- `Time::with` will adjust a specific time component;
- `Time::roundTo` will round the time to a specific unot;
- `Time::clamp` will adjust the time against two other time references;

```php
Time::add(Duration ...$duration): Time
Time::sub(Duration ...$duration): Time
Time::with(
    ?int $hour = null,
    ?int $minute = null,
    ?int $second = null,
    ?int $nanosecond = null
): Time
Time::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): Time
Time::clamp(Time $min, Time $max): Time
```

The `add`, `sub` and `with` methods act differently in regard to wrapping around 24hours.
The `Time::add` and `Time::sub` methods support wrapping whereas `Time::with` does not and instead
throws an `InvalidTime` exception instead.

```php
// adding 2 hours
$time = Time::noon()->add(Duration::of(hours: 2, minutes: 15));
$time->format(TimeFormat::Clock);
// "14:15:00"

// adding 12 hours
$time = Time::noon()->add(Duration::of(hours: 12, minutes: 15));
$time->format(TimeFormat::Clock);
// "00:15:00"

// setting the hour to
$time = Time::noon()->with(hour: 2, minute: 15);
$time->format(TimeFormat::Clock);
// "02:15:00"

Time::noon()->with(hour: 25); 
//throws a Bakame\Tokei\InvalidTime exception
```

To simplify reasoning around time you can also truncate or round its value to one of
the unit declare on the `Bakame\Tokei\Unit` enum

```php
$t = Time::sinceMidnight(Duration::of(microseconds: 3_150_000_000));
$t->format(TimeFormat::Clock); // returns "00:52:30"
$t->roundTo(Unit::Minute, SnapMode::Floor)->format(); // returns "00:52:00"
$t->roundTo(Unit::Minute, SnapMode::Nearest)->format();  // returns "00:53:00"
```

## Comparing times

It is possible to compare two `Time` instances using the `Duration::compare` method.
The method will use the result of `Time::offset` to compare both times.

Convenient methods derived from `Duration::compare` are also available to ease usage:

```php
$time = Time::at(hour: 10);
$other = Time::noon();

Duration::compare($time, $other); // returns -1
$time->isBefore($other);          // returns true
$time->isAfter($other);           // returns false
$time->isBeforeOrEqual($other);   // returns true
$time->isAfterOrEqual($other);    // returns false
$time->equals($other);            // returns false
```

## Differences

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

## Interacting with PHP's native Date API

```php
Time::fromDateTime(DateTimeInterface $datetime): Time
// Extract the time component from a DateTimeInterface instance

Time::applyTo(DateTimeInterface $datetime): DateTimeImmutable
// Apply this time component to a DateTimeInterface instance

Time::now(DateTimeZone|string $timezone): Time
// Extract the current time in the given timezone

Time::today(DateTimeZone|string $timezone): DateTimeImmutable
// Create a DateTimeImmutable for the current date in the given timezone
// with this time component applied

Time::utc(): Time
// Extract the current UTC time
```

<p class="message-notice">
The timezone is required when using <code>Time::now()</code> to
return the current time in a specific timezone. The method
accepts a <code>DateTimeZone</code> instance or a timezone string identifier.
Once the time instance is created, the timezone information is lost.
Conversely, it is required when using <code>Time::today()</code>
to return the current date and time according to a specific timezone.</p>

`Time` represents only the time-of-day component, therefore it does not contain
any date or timezone information by itself.

You can extract this component from any `DateTimeInterface` implementation using
`fromDateTime()`. Conversely, `applyTo()` applies a `Time` instance to an
existing date while preserving the date and timezone information of the supplied
object. If you need a `DateTimeImmutable` representing this time on the current
date, use `today()` with the desired timezone.

<p class="message-warning">If the provided <code>DateTimeInterface</code> instance is or extends the
<code>DateTimeImmutable</code>, the returned object preserves its type. Otherwise, a native PHP
<code>DateTimeImmutable</code> is returned.</p>

```php
use Bakame\Tokei\Time;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

$time = Time::fromDateTime(new DateTime('2025-12-27 23:00', new DateTimeZone('Africa/Nairobi')));
$time->format(TimeFormat::Clock);
// '23:00:00'

$newDate = $time->applyTo(CarbonImmutable::parse('2025-02-23'));
$newDate->format('Y-m-d H:i');
// '2025-02-23 23:00'
$newDate->toDateTimeString();
// '2025-02-23 23:00'
$newDate::class;
// Carbon\CarbonImmutable

$altDate = $time->applyTo(Carbon::parse('2025-02-23'));
$altDate->format('Y-m-d H:i');
// '2025-02-23 23:00'
$altDate::class;
// DateTimeImmutable
$date2 = $time->today('Asia/Tokyo');
// DateTimeImmutable
// an instance from the current date at 23:00 Tokyo time.
```