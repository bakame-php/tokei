# Changelog

All Notable changes to `bakame/tokei` will be documented in this file.

## [Next](https://github.com/bakame-php/stackwatch/compare/0.1.0...main) - TBD

### Added

- `IntervalSet::each`
- `Interval::roundTo`
- `Interval::toNotation`
- `Interval::fromNotation`
- `Interval::truncateTo`
- `Duration::fromDateInterval`
- `Duration::fromNotation`
- `Duration::toNotation`
- `TimeFormatLength` to `Time::toLocaleString` method to allow fine-grained locale string representation supported by `intl` extension
- `IntervalNotation` to improve Interval encoding and decoding from and to string representation.
- `DurationNotation` to improve Duration encoding and decoding from and to string representation.

### Fixed

- `IntervalSet::union` now accept `Interval` and/or `IntervalSet` as arguments to compute the union between sets/intervals
- `IntervalSet::difference` edge cases when dealing with collapsed or circular Intervals.
- `Time::toLocaleString` and `Time::now` accepts timezone string identifier as well as fully instantiated `DateTimeZone` instances.
- Classes and methods accepting `IntervalFormat` now expects `IntervalNotation`
- `Time::toLocaleString` improve timezone handling, the time is no longer affected by the timezone shift.
- **BC BREAK:** `Duration::toNotation` using Chrono format will always output the hours parts with at least two digits previously for hours below 10 one digit was used.
- **BC BREAK:** Renamed method suffixe "Clock" to "Chrono" like in chronometer which is a better description for `Duration`

### Deprecated

- None

### Removed

- **BC BREAK:** `Duration::toClockFormat` is removed and replaced by `Duration::toNotation`
- **BC BREAK:** `Duration::toIso8601` is removed and replaced by `Duration::toNotation`
- **BC BREAK:** `Duration::toCompact` is removed and replaced by `Duration::toNotation`
- **BC BREAK:** `Interval::format` is removed and replaced by `Interval::toNotation`
- **BC BREAK:** `IntervalFormat` is removed and replaced by `IntervalNotation`
- **BC BREAK:** `Interval::fromIso8601` is removed and replaced by `Interval::fromNotation`
- **BC BREAK:** `SubSecondDisplay` is removed with no remplacement.

## [0.1.0 - asagao](https://github.com/bakame-php/tokei/releases/tag/0.1.0) - 2026-05-27

**Initial release!**
