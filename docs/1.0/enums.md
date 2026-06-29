---
layout: default
title: Enums
---

# Package Enums

To work as expected the package defines the following Enums:

## Bound

This Enum is used to indicate which interval boundary is being considered.
An `Interval` has two boundaries at the start and at the end.

```php
enum Bound
{
    case Start;
    case End;
}
```

## Direction

This Enum is used during sorting to indicate which direction ascending or descending
is being considered.

```php
enum Direction
{
    case Ascending;
    case Descending;
}
```

## Unit

The package supported time unit. This enum is helpful to designate which
time unit can be used in the operation.

```php
enum Unit
{
    case Week;
    case Day;
    case Hour;
    case Minute;
    case Second;
    case Millisecond;
    case Microsecond;
}
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



## Snap Mode

This Enum tells how to round a value.

```php
enum SnapMode
{
    case Floor;
    case Nearest;
    case Ceil;
}
```

## Search Mode

This Enum tells which search type is being considered should we do a linear search
with a start and an end or a circular search where there's no beginning or end and
every time are ordered around a time circle.

```php
enum SearchMode
{
    case Linear;
    case Circular;
}
```

## Formatting Enums

### Duration format

To ease choosing the correct string representation for the duration, the `DurationFormat`
Enum is added:

```php
enum DurationFormat
{
    case Iso8601;
    case Timer;
    case Compact;
}
```

- `DurationFormat::Iso8601` : returns a Duration as defined in ISO-8601;
- `DurationFormat::Timer` : returns a Duration represented in a Timer format (`HH:MM:SS.FF`);
- `DurationFormat::Compact` : returns a Duration using a compact view (`1d3h25m3s250us`);

The enum is mostly with formatting methods that uses a `Duration` class.

### Time Format

To ease choosing the correct string representation for a Time, the `TimeFormat` Enum is added:

```php
enum TimeFormat
{
    case Iso8601;
    case Compact;
}
```

- `TimeFormat::Iso8601` : returns a Time string representation as defined in ISO-8601 format (`HH:MM:SS.FF`);
- `TimeFormat::Compact` : returns a Time string representation using a compact view (`3h25m3s250us`);


### Interval format

To ease choosing the correct string representation for an interval, the `IntervalFormat`
Enum is added:

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

- `DurationFormat::Iso8601StartDuration` : returns the Interval string representation using the start boundary and its duration as defined in ISO-8601;
- `DurationFormat::Iso8601DurationEnd` : returns the Interval string representation using its duration and its end boundary as defined in ISO-8601;
- `DurationFormat::Iso8601StartEnd` : returns the Interval string representation using  its start and end boudaries times as defined in ISO-8601;
- `DurationFormat::Iso80000` : returns the Interval string representation using its start and end boudaries times as defined in ISO-80000;
- `DurationFormat::Bourbaki` : returns the Interval string representation using its start and end boudaries times as defined in Bourbaki specification;

The enum is mostly with formatting methods that uses a `Interval` class.

### Locale Verbosity

This Enum allows defining the lenght of the Time when it is being generated using
the `LocalTimeFormatter`.

```php
enum LocaleVerbosity
{
    case Short;
    case Medium;
    case Long;
    case Full;
```