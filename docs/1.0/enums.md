---
layout: default
title: Enums
---

# Package Enums

The following Enums are used throughout the package.

## Bound

This `Bound` enum identifies which interval boundary is being referenced.

```php
enum Bound
{
    case Start;
    case End;
}
```

## Unit

The `Unit` enum defines all time units supported by the package and is used whenever a unit of time must be specified.

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

The `IntervalType` enum defines the four possible interval types based on the relative positions of their boundaries and their duration.

```php
enum IntervalType
{
    case Linear;    // returns true   (start < end)
    case Overflow;  // returns false  (start > end)
    case Circular;  // returns false  (start === end and duration is 'P1D')
    case Collapsed; // returns false  (start === end and duration is 'PT0S')
}
```

The interval types have the following meanings:

- `Linear`: the interval progresses normally from start to end.
- `Overflow`: the interval wraps past midnight.
- `Circular`: the interval spans an entire day.
- `Collapsed`: the interval has no duration.

## Snap Mode

The `SnapMode` enum determines how values should be rounded when snapping.

```php
enum SnapMode
{
    case Floor;
    case Nearest;
    case Ceil;
}
```
Available modes:

- `Floor`: rounds down to the nearest value.
- `Nearest`: rounds to the nearest value.
- `Ceil`: rounds up to the nearest value.

## Search Mode
 
The `SearchMode` enum specifies the search strategy to use.

```php
enum SearchMode
{
    case Linear;
    case Circular;
}
```

Search modes behave as follows:

- `Linear`: searches within a fixed range starting at midnight and ending at the end of the day.
- `Circular`: searches without boundaries and wraps beyond the end of the day.


## Duration format

The `DurationFormat` enum defines the supported string representations for a `Duration`.

```php
enum DurationFormat
{
    case Iso8601;
    case Timer;
    case Compact;
}
```

Available formats:

- `Iso8601`: formats a duration according to the ISO-8601 standard.
- `Timer`: formats a duration using timer notation (`HH:MM:SS.FF`).
- `Compact`: formats a duration using a compact representation (`1d3h25m3s250µs`).

This enum is primarily used by formatting methods operating on `Duration` objects.

## Time Format

The `TimeFormat` enum defines the supported string representations for a `Time`.

```php
enum TimeFormat
{
    case Iso8601;
    case Compact;
}
```

- `Iso8601` : formats a time according to ISO-8601 (`HH:MM:SS.FF`);
- `Compact` : formats a time using a compact representation (`3h25m3s250µs`);

## Interval format

The `IntervalFormat` enum defines the supported string representations for an interval.

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

Available formats to represent an interval in a string format:

- `Iso8601StartDuration` : uses the start boundary and duration, as defined by ISO-8601.
- `Iso8601DurationEnd` : uses the duration and end boundary, as defined by ISO-8601.
- `Iso8601StartEnd` : uses the start and end boundaries, as defined by ISO-8601.
- `Iso80000` : uses the start and end boundaries together with boundary notation according to ISO-80000.
- `Bourbaki` : uses the start and end boundaries together with boundary notation according to Bourbaki notation.

## Locale Verbosity

The `LocaleVerbosity` enum specifies the verbosity level used when generating localized Time string representations with the `LocalTimeFormatter`.

```php
enum LocaleVerbosity
{
    case Short;
    case Medium;
    case Long;
    case Full;
}
```