# Changelog

All Notable changes to `bakame/tokei` will be documented in this file.

## [Next](https://github.com/bakame-php/stackwatch/compare/0.1.0...main) - TBD

### Added

- `Time::fromFormat`
- `Time::format`
- `Time::fromOffset`
- `Time::toOffset`
- `Time::shift`
- `IntervalSet::each`
- `IntervalSet::transform`
- `Interval::fromFormat`
- `Interval::roundTo`
- `Duration::fromDateInterval`
- `Duration::fromFormat`
- `Duration::format`
- `Duration::increase`
- `Duration::decrease`
- `Duration::isZero`
- `LocaleVerbosity` is added to allow fine-grained locale string representation supported by `Time::toLocaleString`
- `IntervalFormat` is improved to better encode and decode `Interval` instances from and to string representation.
- `DurationFormat` is added to improve `Duration` encoding and decoding from and to string representation.
- `TimeFormat` is added to improve `Duration` encoding and decoding from and to string representation.
- `RoundingStrategy` to simplify rounding

### Fixed

- `IntervalSet::union` now accept `Interval` and/or `IntervalSet` as arguments to compute the union between sets/intervals
- `IntervalSet::difference` edge cases when dealing with collapsed or circular Intervals.
- `Time::toLocaleString` and `Time::now` accepts timezone string identifier as well as fully instantiated `DateTimeZone` instances.
- Classes and methods accepting `IntervalFormat` now expects `IntervalNotation`
- `Time::toLocaleString` improve timezone handling, the time is no longer affected by the timezone shift.
- **BC BREAK:** `Duration::toNotation` using Chrono format will always output the hours parts with at least two digits previously for hours below 10 one digit was used.
- **BC BREAK:** Renamed method suffixe "Clock" to "Chrono" like in chronometer which is a better description for `Duration`
- **BC BREAK:** `Duration::of` no longer accepts negative integer use `negated()` of `fromNotation`.
- **BC BREAK:** `Interval::lasting` signature parameter order.
- **BC BREAK:** `Interval::shiftBound` signature parameter order.
- **BC BREAK:** `Unit` enum now only exposes the `inMicroseconds` method all other methods are moved to an internal `UnitTransformer` class.

### Deprecated

- None

### Removed

- **BC BREAK:** `Duration::toClockFormat` is removed and replaced by `Duration::format`
- **BC BREAK:** `Duration::toIso8601` is removed and replaced by `Duration::format`
- **BC BREAK:** `Duration::toCompact` is removed and replaced by `Duration::format`
- **BC BREAK:** `Duration::increment` is removed use `Duration::increase` instead
- **BC BREAK:** `Duration::isEmpty` is removed use `Duration::isZero` instead
- **BC BREAK:** `Interval::fromIso8601` is removed and replaced by `Interval::fromFormat`
- **BC BREAK:** `SubSecondDisplay` is removed with no remplacement.
- **BC BREAK:** `truncateTo` is removed use `roundTo` instead with the new `RoundingStrategy` enum
- **BC BREAK:** `Time::add` is removed use `Time::shift` instead
- **BC BREAK:** `Time::fromUnitOfDay` is removed use `Time::fromOffset` instead
- **BC BREAK:** `Time::toString` is removed use `Time::format` instead

## [0.1.0 - asagao](https://github.com/bakame-php/tokei/releases/tag/0.1.0) - 2026-05-27

**Initial release!**
