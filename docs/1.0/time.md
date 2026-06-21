---
layout: default
title: Time
---

# Time

The `Bakame\Tokei\Time` object is designed to be, cyclic (24h wrap-around) and precision-aware (microseconds supported)

## Instantiation

You can create a `Time` instance:

- using its time components via the `Time::at` method;
- by parsing a time string using the `Time::fromFormat` method;
- using `Time::sinceMidnight`; The value will represent respectively a quantity in a specified base Unit from midnight.

```php
Time::at(
    int $hour = 0,
    int $minute = 0,
    int $second = 0,
    int $microsecond = 0
): Time;

Time::fromFormat(
    string $value,
    TimeFormat $format = TimeFormat::Iso8601
): Time

Time::sinceMidnight(
    int $value,
    Unit $unit
): Time
```

Here's some usage example.

```php
use Bakame\Tokei\Time;

$time = Time::at(hour: 10, minute: 30, second: 15);
$time = Time::fromFormat("10:30:15.123456", TimeFormat::Iso8601);
$time = Time::fromFormat("10h30m15s123456µs", TimeFormat::Compact);
$time = Time::sinceMidnight(123_456_789, Unit::Microsecond);
$time = Time::sinceMidnight(123_456, Unit::Millisecond);
$time = Time::sinceMidnight(123, Unit::Second);
$time = Time::sinceMidnight(-1, Unit::Minute); // returns "23:59:00"
```

To ease instantiation, predefined instances can be obtained with the following methods:

```php 
Time::midnight(); // 00:00:00
Time::noon();     // 12:00:00
Time::endOfDay(); // 23:59:59.999999
Time::utc();      // the UTC current time
Time::now('Africa/Nairobi'); // the current time in Nairobi, Kenya
Time::fromDateTime(new DateTimeImmutable()); // returns the extracted time from any DateTimeInterface instance
```

<p class="message-notice">
The timezone is required when using <code>Time::now()</code> to
return the current time in a specific timezone. The method
accepts a <code>DateTimeZone</code> instance or a timezone string identifier.
Once the time instance is created, the timezone information is lost.</p>


## Accessors

Once instantiated you can access each time component using the following methods

```php
$time = Time::fromFormat("10:30:15.123456");
$time->hour;
// 10
$time->minute;
// 30
$time->second;
// 15
$time->microsecond;
// 123456
```

## Formatting

```php
Time::format(TimeFormat $format = TimeFormat::Iso8601): string
Time::in(Unit $unit): int|float; // returns the time value according to the provided
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
echo $time->format();
// 10:30:15.123456
echo $time->format(TimeFormat::Compact);
// 10h30m15s123456µs
echo $time->in(Unit::Second);
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

- `Time::shift` will adjust the time using a duration;
- `Time::with` will adjust a specific time component;
- `Time::roundTo` will round the time to a specific unot;
- `Time::clamp` will adjust the time against two other time references;

```php
Time::shift(Duration $duration): Time
Time::with(
    ?int $hour = null,
    ?int $minute = null,
    ?int $second = null,
    ?int $microsecond = null
): Time
Time::roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): Time
Time::clamp(Time $min, Time $max): Time
```

The `shift` and `with` methods act differently in regard to wrapping around 24hours.
The `Time::shift` supports wrapping whereas `Time::with` does not and instead
throws an `InvalidTime` exception instead

```php
// adding 2 hours
$time = Time::noon()->shift(Duration::of(hours: 2, minutes: 15));
$time->format();
// "14:15:00"

// adding 12 hours
$time = Time::noon()->shift(Duration::of(hours: 12, minutes: 15));
$time->format();
// "00:15:00"

// setting the hour to
$time = Time::noon()->with(hour: 2);
$time->format();
// "02:15:00"

Time::noon()->with(hour: 25); 
//throws a Bakame\Tokei\InvalidTime exception
```

To simplify reasoning around time you can also truncate or round its value to one of
the unit declare on the `Bakame\Tokei\Unit` enum

```php
$t = Time::sinceMidnight(3_150_000_000, Unit::Microsecond);
$t->format(); // returns "00:52:30"
$t->roundTo(Unit::Minutes, SnapMode::Floor)->format(); // returns "00:52:00"
$t->roundTo(Unit::Minutes, SnapMode::Nearest)->format();  // returns "00:53:00"
```

## Comparing times

It is possible to compare two `Time` instances using the `Time::compareTo` method.

```php
Time::compare(Time $that, Time $other): int;
```
> [!IMPORTANT]
> The method is static to allow broader usage with other PHP sorting functions.


the method returns:

- `-1` if earlier
- `0` if equal
- `1` if later

Convenient methods derived from `Time::compareTo` are also available to ease usage:

```php
$time = Time::at(hour: 10);
$other = Time::noon();

Time::compare($time, $other);    // returns -1
$time->isBefore($other);         // returns true
$time->isAfter($other);          // returns false
$time->isBeforeOrEqual($other);  // returns true
$time->isAfterOrEqual($other);   // returns false
$time->equals($other);           // returns false
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
Time::toDateTime(DateTimeZone|string $timezone): DateTimeImmutable;
Time::applyTo(DateTimeInterface $datetime): DateTimeImmutable;
```

In one hand, it is possible to extract the time part of any `DateTimeInterface`
implementing class using the `fromDateTime` method. On the other hand, you
can apply the time to an `DateTimeInterface` object using the `applyTo` method or get
the time attached to current day in a specific timezone using the `toDateTime` method.

<p class="message-warning">If the <code>DateTimeInterface</code> instance submitted extends the
<code>DateTimeImmutable</code> class then the return type will be of that same type
otherwise PHP's <code>DateTimeImmutable</code> is returned.</p>

```php
use Bakame\Tokei\Time;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

$time = Time::fromDateTime(new DateTime('2025-12-27 23:00', new DateTimeZone('Africa/Nairobi'))); // 23:00

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
$date2 = $time->toDateTime('Asia/Tokyo');
// DateTimeImmutable
// an instance from the current date at 23:00 Tokyo time.
```

<p class="message-warning">
Whenever an API expects a <code>Time</code> instance, a <code>DateTimeInterface</code> instance can be used.
It will be converted to a <code>Time</code> instance using the <code>Time::fromDateTime</code> method.
</p>
